<?php

use Kirby\Cms\App as Kirby;
use Kirby\Cms\Find;
use Kirby\Cms\Page;
use Kirby\Exception\NotFoundException;
use Kirby\Toolkit\I18n;
use Medienbaecker\FileUsage\FileUsage;

require_once __DIR__ . '/classes/FileUsage.php';

$dropdown = function (string $parent, string $filename): array {
	$file = Find::file($parent, $filename);
	$options = $file->panel()->dropdown();
	$usage = [
		'dialog' => 'file-usage/' . $file->uuid()->id(),
		'icon'   => 'search',
		'text'   => I18n::translate('medienbaecker.file-usage.title'),
	];

	array_splice($options, array_search('-', $options, true), 0, [$usage]);

	return $options;
};

Kirby::plugin('medienbaecker/file-usage', [
	'options' => [
		'render' => fn (Page $page) => true,
	],
	'translations' => require __DIR__ . '/translations.php',
	'areas' => [
		'site' => fn () => [
			'dropdowns' => [
				'page.file' => [
					'pattern' => '(pages/[^/]+)/files/(:any)',
					'options' => $dropdown,
				],
				'site.file' => [
					'pattern' => '(site)/files/(:any)',
					'options' => $dropdown,
				],
			],
			'dialogs' => [
				'file-usage' => [
					'pattern' => 'file-usage/(:any)',
					'load' => function (string $id) {
						$items = [];
						$queue = [];
						$render = option('medienbaecker.file-usage.render');
						$pages = site()->index(true);

						foreach ($pages as $page) {
							if ($languages = FileUsage::inContent($page, $id)) {
								$items[] = FileUsage::item($page, $languages);
							} elseif ($render($page)) {
								$queue[] = $page->id();
							}
						}

						return [
							'component' => 'k-file-usage-dialog',
							'props' => [
								'file'         => $id,
								'items'        => $items,
								'queue'        => $queue,
								'total'        => $pages->count(),
								'size'         => 'medium',
								'submitButton' => false,
								'cancelButton' => I18n::translate('close'),
							],
						];
					},
				],
			],
		],
	],
	'api' => [
		'routes' => [
			[
				'pattern' => 'file-usage/render',
				'method'  => 'GET',
				'action'  => function () {
					$kirby = $this->kirby();
					$file = $kirby->file('file://' . $this->requestQuery('file'));

					if (!$file) {
						throw new NotFoundException('The file could not be found');
					}

					$items = [];
					$failed = [];

					foreach (explode(',', $this->requestQuery('pages', '')) as $id) {
						if (!$page = $kirby->site()->findPageOrDraft($id)) {
							continue;
						}

						$languages = FileUsage::inOutput($page, $file);

						if ($languages === null) {
							$failed[] = $page->title()->value();
						} elseif ($languages) {
							$items[] = FileUsage::item($page, $languages);
						}
					}

					return ['items' => $items, 'failed' => $failed];
				},
			],
		],
	],
]);
