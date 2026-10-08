<?php

declare(strict_types=1);

namespace Minilytics\Importers;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Base Importer Class with shared normalization utilities.
 */
abstract class BaseImporter implements ImporterInterface
{
    protected static array $countryMap = [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra',
        'AO' => 'Angola', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia',
        'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BS' => 'Bahamas',
        'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BT' => 'Bhutan',
        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil',
        'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
        'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde',
        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China',
        'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (DRC)',
        'CR' => 'Costa Rica', 'CI' => 'Ivory Coast', 'HR' => 'Croatia', 'CU' => 'Cuba',
        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti',
        'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador',
        'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FI' => 'Finland', 'FR' => 'France',
        'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
        'GH' => 'Ghana', 'GR' => 'Greece', 'GT' => 'Guatemala', 'GN' => 'Guinea',
        'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungary', 'IS' => 'Iceland',
        'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
        'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica',
        'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 'KE' => 'Kenya',
        'KR' => 'South Korea', 'KW' => 'Kuwait', 'LV' => 'Latvia', 'LB' => 'Lebanon',
        'LY' => 'Libya', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MG' => 'Madagascar',
        'MY' => 'Malaysia', 'ML' => 'Mali', 'MT' => 'Malta', 'MR' => 'Mauritania',
        'MU' => 'Mauritius', 'MX' => 'Mexico', 'MD' => 'Moldova', 'MC' => 'Monaco',
        'MN' => 'Mongolia', 'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique',
        'NA' => 'Namibia', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand',
        'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NO' => 'Norway',
        'OM' => 'Oman', 'PK' => 'Pakistan', 'PA' => 'Panama', 'PY' => 'Paraguay',
        'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal',
        'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda',
        'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SG' => 'Singapore',
        'SK' => 'Slovakia', 'SI' => 'Slovenia', 'ZA' => 'South Africa', 'ES' => 'Spain',
        'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'SY' => 'Syria', 'TW' => 'Taiwan', 'TZ' => 'Tanzania', 'TH' => 'Thailand',
        'TG' => 'Togo', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'UG' => 'Uganda',
        'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
        'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VE' => 'Venezuela',
        'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
        'MQ' => 'Martinique', 'GP' => 'Guadeloupe', 'RE' => 'Reunion', 'GF' => 'French Guiana',
    ];

    public function getCountryName(string $code): string
    {
        $clean = strtoupper(trim($code));
        return self::$countryMap[$clean] ?? $clean;
    }

    public function normalizeBrowser(?string $browser): string
    {
        if (empty($browser) || $browser === '\N') {
            return 'Unknown';
        }
        $b = strtolower(trim($browser));

        if ($b === 'ios' || $b === 'safari') {
            return 'Safari';
        }
        if ($b === 'crios') {
            return 'Chrome (iOS)';
        }
        if ($b === 'fxios') {
            return 'Firefox (iOS)';
        }
        if ($b === 'edge-ios') {
            return 'Edge (iOS)';
        }
        if (str_contains($b, 'edge')) {
            return 'Edge';
        }
        if (str_contains($b, 'chrome')) {
            return 'Chrome';
        }
        if (str_contains($b, 'firefox')) {
            return 'Firefox';
        }
        if (str_contains($b, 'opera') || str_contains($b, 'opr')) {
            return 'Opera';
        }
        if (str_contains($b, 'samsung')) {
            return 'Samsung Internet';
        }
        if (str_contains($b, 'webview')) {
            return 'WebView';
        }
        if ($b === 'facebook') {
            return 'Facebook In-App';
        }
        if ($b === 'instagram') {
            return 'Instagram In-App';
        }
        if ($b === 'android') {
            return 'Android Browser';
        }

        return ucfirst($b);
    }

    public function normalizeOs(?string $os): string
    {
        if (empty($os) || $os === '\N') {
            return 'Unknown';
        }
        $o = trim($os);
        $low = strtolower($o);

        if ($low === 'mac os' || $low === 'macos' || $low === 'os x') {
            return 'macOS';
        }
        if (str_starts_with($low, 'windows')) {
            return $o;
        }
        if ($low === 'android os' || $low === 'android') {
            return 'Android';
        }
        if ($low === 'ios') {
            return 'iOS';
        }
        if ($low === 'linux') {
            return 'Linux';
        }
        if (str_contains($low, 'chrome os')) {
            return 'Chrome OS';
        }

        return $o;
    }

    public function normalizeDevice(?string $device): string
    {
        if (empty($device) || $device === '\N') {
            return 'Desktop';
        }
        $d = strtolower(trim($device));

        if ($d === 'mobile') {
            return 'Mobile';
        }
        if ($d === 'tablet') {
            return 'Tablet';
        }
        if ($d === 'laptop' || $d === 'desktop') {
            return 'Desktop';
        }

        return ucfirst($d);
    }

    public function cleanVal($val, $default = null)
    {
        if ($val === null || $val === '' || $val === '\N') {
            return $default;
        }
        return $val;
    }

    /**
     * Extracts a zip archive to a target directory.
     * Uses ZipArchive if available, or PowerShell Expand-Archive fallback on Windows.
     */
    public function extractZip(string $zipPath, string $targetDir): bool
    {
        if (!file_exists($zipPath)) {
            throw new InvalidArgumentException("Zip file does not exist: {$zipPath}");
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            $res = $zip->open($zipPath);
            if ($res === true) {
                $zip->extractTo($targetDir);
                $zip->close();
                return true;
            }
            throw new RuntimeException("ZipArchive failed to open file (code {$res}): {$zipPath}");
        }

        // Fallback for Windows if ZipArchive extension is disabled in php.ini
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = sprintf(
                'powershell -NoProfile -NonInteractive -Command "Expand-Archive -LiteralPath %s -DestinationPath %s -Force"',
                escapeshellarg($zipPath),
                escapeshellarg($targetDir),
            );
            $out = [];
            $ret = 0;
            exec($cmd, $out, $ret);
            if ($ret === 0) {
                return true;
            }
        }

        throw new RuntimeException("ZipArchive extension is not enabled in PHP and system fallback failed.");
    }

    /**
     * Recursively delete a directory
     */
    public function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
