<?php

declare(strict_types=1);

namespace Minilytics\Database;

use Minilytics\Analytics\SiteOverview;
use Minilytics\Sites\SiteRegistry;

/**
 * The former entry point to storage, split by responsibility. Each method now
 * calls the class that took it over, until the callers use them directly.
 *
 * @deprecated Use DataDirectory, DatabaseConfig, Connections, SiteRegistry or SiteOverview.
 */
class Database
{
    public static function getDataDir(): string
    {
        return DataDirectory::path();
    }

    public static function getSitesFilePath(): string
    {
        return DataDirectory::path() . '/sites.json';
    }

    public static function getDatabaseConfigPath(): string
    {
        return DataDirectory::path() . '/database.json';
    }

    public static function getDatabaseConfig(): array
    {
        return DatabaseConfig::load();
    }

    public static function publicDatabaseConfig(): array
    {
        return DatabaseConfig::publicView();
    }

    public static function saveDatabaseConfig(array $input): array
    {
        return DatabaseConfig::save($input);
    }

    public static function testDatabaseConfig(array $input): array
    {
        return DatabaseConfig::test($input);
    }

    public static function sanitizeSiteId(?string $siteId): string
    {
        return SiteRegistry::requireId($siteId);
    }

    public static function getAvailableSites(): array
    {
        return SiteRegistry::all();
    }

    public static function getSitesWithStats(): array
    {
        return SiteOverview::all();
    }

    public static function saveSites(array $sites): void
    {
        SiteRegistry::save($sites);
    }

    public static function normalizeHost(string $value): string
    {
        return SiteRegistry::normalizeHost($value);
    }

    public static function trackingSite(string $siteId): ?array
    {
        return SiteRegistry::find($siteId);
    }

    public static function isPublicSite(?string $siteId): bool
    {
        return SiteRegistry::isPublic($siteId);
    }

    public static function getPublicSite(?string $siteId = null): ?array
    {
        return SiteRegistry::findPublic($siteId);
    }

    public static function guestSiteView(array $site): array
    {
        return SiteRegistry::guestView($site);
    }

    public static function trackingSnippet(array $site, string $scriptUrl): string
    {
        return SiteRegistry::trackingSnippet($site, $scriptUrl);
    }

    public static function updateSiteConfig(string $siteId, array $input): array
    {
        return SiteRegistry::update($siteId, $input);
    }

    public static function createSite(string $siteId, string $name, string $domain = ''): array
    {
        return SiteRegistry::create($siteId, $name, $domain);
    }

    public static function deleteSite(string $siteId): bool
    {
        return SiteRegistry::delete($siteId);
    }

    public static function getConnection(?string $siteId = null): DatabaseConnection
    {
        return Connections::forSite($siteId);
    }
}
