<?php

namespace Drupal\lts_download;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Handles deduplicated logging of installer downloads.
 *
 * A DownloadLog row's existence IS the record of acquisition — there is
 * no "completed" flag. Dedup key is (uid, file_id): the same user
 * downloading the same file again does not create a new row. If the
 * installer file is replaced (a new managed file entity), the next
 * download correctly logs as a new acquisition automatically.
 *
 * See: download-workflow-logging-design.md, §3 and §4.1.
 */
class DownloadLogger {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Records a download if this (uid, file) pair hasn't been logged yet.
   *
   * @param \Drupal\node\NodeInterface $software
   *   The software node.
   * @param \Drupal\paragraphs\ParagraphInterface $installer_file
   *   The chosen installer_file paragraph (holds OS, variant, file).
   * @param int $uid
   *   The downloading user's ID.
   * @param string $ip_address
   *   The request's IP address.
   *
   * @return bool
   *   TRUE if a new log row was written, FALSE if this was already logged.
   */
  public function logDownload(NodeInterface $software, ParagraphInterface $installer_file, int $uid, string $ip_address): bool {

    if ($installer_file->get('field_installer_file')->isEmpty()) {
      // Nothing to log if there's no actual file attached — shouldn't
      // happen if the controller validated first, but don't write a
      // broken row.
      return FALSE;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $installer_file->get('field_installer_file')->entity;
    if (!$file instanceof FileInterface) {
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage('download_log');

    // Dedup check: has this user already logged this exact file?
    $existing = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('file_id', $file->id())
      ->range(0, 1)
      ->execute();

    if (!empty($existing)) {
      // Already logged. Same rule you set: repeated clicks on an
      // unchanged file are not new acquisitions.
      return FALSE;
    }

    $os = $installer_file->get('field_installer_os')->value;

    $variant_label = NULL;
    if (!$installer_file->get('field_installer_variant')->isEmpty()) {
      $variant_term = $installer_file->get('field_installer_variant')->entity;
      $variant_label = $variant_term ? $variant_term->label() : NULL;
    }

    $log = $storage->create([
      'uid' => $uid,
      'software' => $software->id(),
      'os' => $os,
      'variant' => $variant_label,
      'filename' => $file->getFilename(),
      'file_id' => $file->id(),
      'department' => $this->resolveDepartment($uid),
      'ip_address' => $ip_address,
    ]);
    $log->save();

    return TRUE;
  }

  /**
   * Resolves the downloading user's department.
   *
   * // POC STUB: No LDAP/SSO integration exists yet in this PoC — SSO
   * itself is currently stubbed as plain Drupal roles (see SACRED
   * handoff doc, POC STUBS section). When real LDAP/SSO is wired in for
   * production, replace this method's body with the actual attribute
   * lookup (e.g. via ldap_servers or the SSO service in use at that
   * time). Faculty/staff should yield a real department string;
   * students are expected to yield "student.<institution>" per the
   * known limitation documented in the design doc, §4.3 — this is not
   * a bug to fix here, just the ceiling of what LDAP provides for
   * students today.
   *
   * @param int $uid
   *   The user ID.
   *
   * @return string|null
   *   The department, or NULL until real integration exists.
   */
  protected function resolveDepartment(int $uid): ?string {
    return NULL;
  }

}
