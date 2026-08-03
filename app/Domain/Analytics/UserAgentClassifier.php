<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

final class UserAgentClassifier
{
    public function classify(string $userAgent): UserAgentClassification
    {
        // Intentional counterpart of public/px.php::tm_classify_user_agent; the collector cannot autoload this class (§5.4).
        $value = strtolower($userAgent);
        foreach (['bot', 'crawl', 'spider', 'preview', 'headless', 'curl/', 'wget'] as $needle) {
            if (str_contains($value, $needle)) {
                return new UserAgentClassification(true, 'bot', 'bot', 'unknown');
            }
        }

        $device = match (true) {
            str_contains($value, 'ipad'), str_contains($value, 'tablet') => 'tablet',
            str_contains($value, 'mobile'), str_contains($value, 'iphone'), str_contains($value, 'android') => 'mobile',
            str_contains($value, 'smart-tv'), str_contains($value, 'hbbtv') => 'tv',
            str_contains($value, 'windows'), str_contains($value, 'mac os'), str_contains($value, 'linux') => 'desktop',
            default => 'unknown',
        };
        $browser = match (true) {
            str_contains($value, 'edg/') => 'edge',
            str_contains($value, 'firefox/') => 'firefox',
            str_contains($value, 'chrome/'), str_contains($value, 'crios/') => 'chrome',
            str_contains($value, 'safari/') => 'safari',
            default => 'unknown',
        };
        $os = match (true) {
            str_contains($value, 'android') => 'android',
            str_contains($value, 'iphone'), str_contains($value, 'ipad'), str_contains($value, 'cpu os') => 'ios',
            str_contains($value, 'windows') => 'windows',
            str_contains($value, 'mac os') => 'macos',
            str_contains($value, 'linux') => 'linux',
            default => 'unknown',
        };

        return new UserAgentClassification(false, $device, $browser, $os);
    }
}
