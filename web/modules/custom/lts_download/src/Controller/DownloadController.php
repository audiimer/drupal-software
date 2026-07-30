<?php

namespace Drupal\lts_download\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\lts_download\DownloadLogger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Serves installer downloads directly, without cart/checkout.
 *
 * Replaces the old client-side "point at the raw file URL" behavior
 * (see design doc §4, "why this matters for logging"). This controller
 * is the single server-side moment where access is checked and the
 * download is logged before the file is streamed back.
 */
class DownloadController extends ControllerBase {

  protected DownloadLogger $logger;
  protected RequestStack $requestStack;

  public function __construct(DownloadLogger $logger, RequestStack $request_stack) {
    $this->logger = $logger;
    $this->requestStack = $request_stack;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lts_download.logger'),
      $container->get('request_stack')
    );
  }

  /**
   * Access callback for the download route.
   *
   * POC STUB: access is currently "any authenticated user." Production
   * should tighten this to whatever role/permission gate WMS's real
   * SSO/licensing model requires — see SACRED handoff doc, POC STUBS
   * section, re: SSO-as-roles.
   */
  public function access(NodeInterface $node, ParagraphInterface $installer_file, AccountInterface $account) {
    if (!$account->isAuthenticated()) {
      return AccessResult::forbidden('Must be logged in to download.');
    }

    if ($node->bundle() !== 'software') {
      return AccessResult::forbidden('Not a software node.');
    }

    // Confirm this installer_file paragraph actually belongs to this
    // node's installer_modal, so a user can't request an arbitrary
    // paragraph ID unrelated to the software they're viewing.
    if (!$this->belongsToNode($node, $installer_file)) {
      return AccessResult::forbidden('Installer does not belong to this software.');
    }

    return AccessResult::allowed();
  }

  /**
   * Delivers the file and logs the download.
   */
  public function deliver(NodeInterface $node, ParagraphInterface $installer_file) {
    if ($installer_file->get('field_installer_file')->isEmpty()) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('No installer file attached.');
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $installer_file->get('field_installer_file')->entity;
    if (!$file instanceof FileInterface) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Installer file not found.');
    }

    $uid = (int) $this->currentUser()->id();
    $ip = $this->requestStack->getCurrentRequest()->getClientIp() ?? '0.0.0.0';

    // Log BEFORE streaming: if logging fails, we want an exception,
    // not a silent unlogged download.
    $this->logger->logDownload($node, $installer_file, $uid, $ip);

    $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());

    $response = new BinaryFileResponse($real_path);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $file->getFilename()
    );

    return $response;
  }

  /**
   * Confirms an installer_file paragraph belongs to the given node.
   */
  protected function belongsToNode(NodeInterface $node, ParagraphInterface $installer_file): bool {
    // field_installer_files is the flat field directly on the Software
    // node (the old nested field_installer_modal -> field_installer_files
    // path is retired -- see design doc, flattening decision).
    if (!$node->hasField('field_installer_files')) {
      return FALSE;
    }

    foreach ($node->get('field_installer_files')->referencedEntities() as $entry) {
      if ((int) $entry->id() === (int) $installer_file->id()) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
