<?php

declare(strict_types=1);

namespace Minilytics\Importers;
use RuntimeException;


/**
 * Matomo Analytics Importer — Coming Soon
 */
class MatomoImporter extends BaseImporter {
    public function getId(): string {
        return 'matomo';
    }

    public function getName(): string {
        return 'Matomo';
    }

    public function getDescription(): string {
        return 'Import visits, actions and logs from Matomo / Piwik database dumps or CSV archives.';
    }

    public function getStatus(): string {
        return 'soon';
    }

    public function getBadge(): string {
        return 'Coming Soon';
    }

    public function getIcon(): string {
        return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';
    }

    public function isAvailable(): bool {
        return false;
    }

    public function getSupportedFormats(): array {
        return ['.zip', '.csv', '.sql'];
    }

    public function import(string $sourcePath, string $siteId, array $options = []): array {
        throw new RuntimeException("Matomo importer is coming soon.");
    }
}
