<?php

namespace Medienbaecker\FileUsage;

use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Toolkit\I18n;
use Throwable;

class FileUsage
{
	/**
	 * @return array<string>
	 */
	public static function languages(): array
	{
		return kirby()->multilang() ? kirby()->languages()->codes() : ['default'];
	}

	/**
	 * @return array<string>
	 */
	public static function inContent(Page $page, string $id): array
	{
		$found = [];

		foreach (static::languages() as $code) {
			foreach (['latest', 'changes'] as $versionId) {
				$version = $page->version($versionId);

				if ($version->exists($code) && str_contains(json_encode($version->read($code) ?? []), $id)) {
					$found[] = $code;
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * @return array<string>|null
	 */
	public static function inOutput(Page $page, File $file): array|null
	{
		$kirby = kirby();
		$language = $kirby->language()?->code();
		$locale = I18n::locale();
		$found = [];

		try {
			foreach (static::languages() as $code) {
				$kirby->site()->visit($page, $code === 'default' ? null : $code);

				$html = $page->render(['fileUsage' => true]);

				if (str_contains($html, $file->mediaHash())) {
					$found[] = $code;
				}
			}
		} catch (Throwable) {
			return null;
		} finally {
			$kirby->setCurrentLanguage($language);
			$kirby->setCurrentTranslation($locale);
		}

		return $found;
	}

	/**
	 * @param array<string> $languages
	 */
	public static function item(Page $page, array $languages): array
	{
		$info = array_filter([implode(' / ', $page->parents()->flip()->pluck('title'))]);

		if ($page->isDraft()) {
			$info[] = I18n::translate('medienbaecker.file-usage.draft');
		}

		if (kirby()->multilang() && count($languages) < count(static::languages())) {
			$info[] = implode(', ', array_map('strtoupper', $languages));
		}

		return $page->panel()->pickerData([
			'info' => implode(' · ', $info),
		]);
	}
}
