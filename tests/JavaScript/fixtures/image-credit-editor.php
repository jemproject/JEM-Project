<?php

/** Read-only Joomla bootstrap: render repository fields, never install or save data. */
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
require $root . '/tests/bootstrap.php';
require $root . '/tests/Joomla/ImageMetadataJoomlaIntegrationTest.php';
ImageMetadataJoomlaIntegrationTest::setUpBeforeClass();
require $root . '/site/models/fields/jemimagemetadata.php';
Joomla\CMS\Factory::getApplication()->getLanguage()->load('com_jem', $root . '/site', 'en-GB', true);
$html = '';
foreach (['site', 'admin'] as $client) {
    foreach (['event', 'venue', 'category'] as $context) {
        foreach (['essential', 'advanced'] as $profile) {
            JemConfig::getInstance()->toRegistry()->set('operating_profile', $profile);
            $key = $client . '_' . $context . '_' . $profile;
            $form = Joomla\CMS\Form\Form::getInstance($key, $root . '/' . $client . '/models/forms/' . $context . '.xml', ['control' => $key]);
            $metadata = [];
            foreach (JemImageMetadata::slots($context) as $slot) {
                $metadata[$slot] = ['caption' => 'Museum workshop', 'credit' => 'Jane Smith, <a href="https://example.org/source">Museum</a>, CC BY-SA 4.0'];
            }
            $form->bind(['image_metadata' => $metadata]);
            $fields = $form->getInput('image_metadata');
            if (($profile === 'advanced') !== str_contains($fields, 'data-jem-credit-editor')) {
                throw new RuntimeException('Unexpected profile controls: ' . $key);
            }
            if ($profile === 'advanced') {
                $html .= '<form class="jem-image-upload-panel" id="' . $key . '"><h2>' . $key . '</h2>' . $fields
                    . '<button type="button" class="jem-image-clear">Clear</button></form>';
                foreach ([['caption' => 'Caption only'], ['credit' => 'Credit only'], []] as $entry) {
                    $probe = clone $form;
                    $probe->setValue('image_metadata', null, [$slot => $entry]);
                    $rendered = $probe->getInput('image_metadata');
                    if (str_contains($rendered, 'class="jem-image-metadata__details" open') !== ($entry !== [])) {
                        throw new RuntimeException('Unexpected disclosure state: ' . $key);
                    }
                }
            }
        }
    }
}
$empty = Joomla\CMS\Form\Form::getInstance('empty_credit', $root . '/site/models/forms/category.xml', ['control' => 'empty_credit']);
$html .= '<form id="empty_credit">' . $empty->getInput('image_metadata') . '</form>';
echo json_encode(['html' => $html, 'formChecks' => 12], JSON_THROW_ON_ERROR);
