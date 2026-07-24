<?php

declare(strict_types=1);

namespace DbflowLabs\Filament\Tests\Feature;

use DbflowLabs\Filament\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DBFlowFilamentTranslationsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const LOCALES = [
        'en',
        'zh_CN',
        'zh_TW',
        'ja',
        'fr',
        'de',
        'es',
        'pt_BR',
    ];

    #[Test]
    public function translation_files_exist_for_supported_locales(): void
    {
        foreach (self::LOCALES as $locale) {
            $this->assertFileExists(__DIR__."/../../lang/{$locale}/dbflow-filament.php");
        }
    }

    #[Test]
    public function supported_locales_match_english_translation_keys(): void
    {
        $englishKeys = $this->flattenTranslationKeys(
            require __DIR__.'/../../lang/en/dbflow-filament.php',
        );

        foreach (array_slice(self::LOCALES, 1) as $locale) {
            $localeKeys = $this->flattenTranslationKeys(
                require __DIR__."/../../lang/{$locale}/dbflow-filament.php",
            );

            $this->assertSame(
                $englishKeys,
                $localeKeys,
                "Translation keys for [{$locale}] must match the English baseline.",
            );
        }
    }

    #[Test]
    public function baseline_translation_keys_resolve(): void
    {
        $this->assertSame('Workflows', trans('dbflow-filament::dbflow-filament.navigation.group'));
        $this->assertSame('My Workflow Tasks', trans('dbflow-filament::dbflow-filament.pages.my_tasks.title'));
        $this->assertSame('Approve', trans('dbflow-filament::dbflow-filament.actions.approve'));
        $this->assertSame('Pending Approval', trans('dbflow-filament::dbflow-filament.statuses.pending'));
        $this->assertSame('Workflow Definitions', trans('dbflow-filament::dbflow-filament.resources.workflow_definitions.plural_model_label'));
        $this->assertSame('Check Configuration', trans('dbflow-filament::dbflow-filament.actions.definitions.validate_draft'));
    }

    #[Test]
    public function localized_navigation_group_resolves_when_locale_is_set(): void
    {
        $zhCn = require __DIR__.'/../../lang/zh_CN/dbflow-filament.php';
        $ja = require __DIR__.'/../../lang/ja/dbflow-filament.php';
        $fr = require __DIR__.'/../../lang/fr/dbflow-filament.php';

        app()->setLocale('zh_CN');
        $this->assertSame($zhCn['navigation']['group'], trans('dbflow-filament::dbflow-filament.navigation.group'));

        app()->setLocale('ja');
        $this->assertSame($ja['navigation']['group'], trans('dbflow-filament::dbflow-filament.navigation.group'));

        app()->setLocale('fr');
        $this->assertSame($fr['navigation']['group'], trans('dbflow-filament::dbflow-filament.navigation.group'));

        app()->setLocale('en');
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return list<string>
     */
    private function flattenTranslationKeys(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenTranslationKeys($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }
}
