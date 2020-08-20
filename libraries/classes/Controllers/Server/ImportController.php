<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Server;

use PhpMyAdmin\Charsets;
use PhpMyAdmin\Charsets\Charset;
use PhpMyAdmin\Common;
use PhpMyAdmin\Config\PageSettings;
use PhpMyAdmin\Controllers\AbstractController;
use PhpMyAdmin\Core;
use PhpMyAdmin\Display\ImportAjax;
use PhpMyAdmin\Encoding;
use PhpMyAdmin\Import;
use PhpMyAdmin\Message;
use PhpMyAdmin\Plugins;
use PhpMyAdmin\Util;
use function intval;

final class ImportController extends AbstractController
{
    public function index(): void
    {
        global $db, $max_upload_size, $table, $SESSION_KEY, $cfg;

        $pageSettings = new PageSettings('Import');
        $pageSettingsErrorHtml = $pageSettings->getErrorHTML();
        $pageSettingsHtml = $pageSettings->getHTML();

        $this->addScriptFiles(['import.js']);

        Common::server();

        [$SESSION_KEY, $uploadId] = ImportAjax::uploadProgressSetup();

        $importList = Plugins::getImport('server');

        if (empty($importList)) {
            $this->response->addHTML(Message::error(__(
                'Could not load import plugins, please check your installation!'
            ))->getDisplay());

            return;
        }

        $offset = null;
        if (Core::isValid($_REQUEST['offset'], 'numeric')) {
            $offset = intval($_REQUEST['offset']);
        }

        $timeoutPassed = $_REQUEST['timeout_passed'] ?? null;
        $localImportFile = $_REQUEST['local_import_file'] ?? null;
        $compressions = Import::getCompressions();

        $allCharsets = Charsets::getCharsets($this->dbi, $cfg['Server']['DisableIS']);
        $charsets = [];
        /** @var Charset $charset */
        foreach ($allCharsets as $charset) {
            $charsets[] = [
                'name' => $charset->getName(),
                'description' => $charset->getDescription(),
            ];
        }

        $idKey = $_SESSION[$SESSION_KEY]['handler']::getIdKey();
        $hiddenInputs = [
            $idKey => $uploadId,
            'import_type' => 'server',
        ];

        $this->render('server/import/index', [
            'page_settings_error_html' => $pageSettingsErrorHtml,
            'page_settings_html' => $pageSettingsHtml,
            'upload_id' => $uploadId,
            'handler' => $_SESSION[$SESSION_KEY]['handler'],
            'pma_theme_image' => $GLOBALS['pmaThemeImage'],
            'hidden_inputs' => $hiddenInputs,
            'db' => $db,
            'table' => $table,
            'max_upload_size' => $max_upload_size,
            'import_list' => $importList,
            'local_import_file' => $localImportFile,
            'is_upload' => $GLOBALS['is_upload'],
            'upload_dir' => $cfg['UploadDir'] ?? null,
            'timeout_passed_global' => $GLOBALS['timeout_passed'] ?? null,
            'compressions' => $compressions,
            'is_encoding_supported' => Encoding::isSupported(),
            'encodings' => Encoding::listEncodings(),
            'import_charset' => $cfg['Import']['charset'] ?? null,
            'timeout_passed' => $timeoutPassed,
            'offset' => $offset,
            'can_convert_kanji' => Encoding::canConvertKanji(),
            'charsets' => $charsets,
            'is_foreign_key_check' => Util::isForeignKeyCheck(),
            'user_upload_dir' => Util::userDir($cfg['UploadDir'] ?? ''),
            'local_files' => Import::getLocalFiles($importList),
        ]);
    }
}
