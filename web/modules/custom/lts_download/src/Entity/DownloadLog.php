<?php

namespace Drupal\lts_download\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\Annotation\ContentEntityType;

/**
 * Defines the Download Log entity.
 *
 * Records one row per unique (uid, file) acquisition of a software
 * installer. Existence of a row IS the record of acquisition — there is
 * no "completed" flag, by design (see project handoff doc, Phase 4/5).
 *
 * @ContentEntityType(
 *   id = "download_log",
 *   label = @Translation("Download Log"),
 *   base_table = "download_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   admin_permission = "administer software download logs",
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 * )
 */
class DownloadLog extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user who downloaded the installer.'))
      ->setSetting('target_type', 'user')
      ->setRequired(TRUE);

    $fields['software'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Software'))
      ->setDescription(t('The software node this installer belongs to.'))
      ->setSetting('target_type', 'node')
      ->setRequired(TRUE);

    $fields['os'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operating System'))
      ->setDescription(t('Snapshot of the OS value (windows/macos/linux) at download time.'))
      ->setSetting('max_length', 32)
      ->setRequired(TRUE);

    $fields['variant'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Installer Variant'))
      ->setDescription(t('Snapshot of the variant taxonomy term label (e.g. x64, ARM64) at download time.'))
      ->setSetting('max_length', 64);

    $fields['filename'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Filename'))
      ->setDescription(t('Snapshot of the installer filename at download time, so historical reports stay readable even if the file is later replaced or deleted.'))
      ->setSetting('max_length', 255)
      ->setRequired(TRUE);

    $fields['file_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('File'))
      ->setDescription(t('The managed file entity delivered. This is the dedup key: a new file (installer update) produces a new log row for the same user.'))
      ->setSetting('target_type', 'file')
      ->setRequired(TRUE);

    $fields['department'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Department'))
      ->setDescription(t('LDAP department attribute at download time. Faculty/staff show a real department; students currently show "student.<institution>" — a known limitation, not a bug (see design doc §4.3).'))
      ->setSetting('max_length', 255);

    $fields['ip_address'] = BaseFieldDefinition::create('string')
      ->setLabel(t('IP Address'))
      ->setDescription(t('Request IP at download time. Kept for audit and as a hook for potential future on/off-campus reporting.'))
      ->setSetting('max_length', 45);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Downloaded'))
      ->setDescription(t('The time the download was granted.'));

    return $fields;
  }

}
