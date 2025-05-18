<?php

namespace Yunusbek\Multilingual\commands;

use Yii;
use yii\base\InvalidConfigException;
use yii\console\Exception;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\BaseConsole;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use Yunusbek\Multilingual\components\ExcelExportImport;
use Yunusbek\Multilingual\models\BaseLanguageList;
use Yunusbek\Multilingual\models\BaseLanguageQuery;

/**
 * Usage:
 * 1. Create a configuration file using the 'message/config' command:
 *    yii message/config /path/to/myapp/messages/config.php
 *    yii attribute/config /path/to/myapp/attribute/config.php
 * 2. Edit the created config file, adjusting it for your web application needs.
 * 3. Run the 'message/extract' command, using created config:
 *    yii message /path/to/myapp/messages/config.php
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Messages extends \yii\console\Controller
{
    /**
     * @var string controller default action ID.
     */
    public $defaultAction = 'extract';
    /**
     * @var string required, root directory of all source files.
     */
    public $sourcePath = '@yii';
    /**
     * @var string required, name of json file.
     */
    public $json_file_name = 'multilingual';
    /**
     * @var string required, json file.
     */
    public $jsonData = [];
    public $jsonError = false;
    /**
     * @var string required, root directory containing message translations.
     */
    public $messagePath = '@yii/messages';
    /**
     * @var array required, list of language codes that the extracted messages
     * should be translated to. For example, ['zh-CN', 'de'].
     */
    public $languages = [];
    /**
     * @var string|string[] the name of the function for translating messages.
     * This is used as a mark to find the messages to be translated.
     * You may use a string for single function name or an array for multiple function names.
     */
    public $translator = ['Yii::t', '\Yii::t'];
    /**
     * @var bool whether to sort messages by keys when merging new messages
     * with the existing ones. Defaults to false, which means the new (untranslated)
     * messages will be separated from the old (translated) ones.
     */
    public $sort = false;
    /**
     * @var bool whether the message file should be overwritten with the merged messages
     */
    public $overwrite = true;
    /**
     * @var bool whether to remove messages that no longer appear in the source code.
     * Defaults to false, which means these messages will NOT be removed.
     */
    public $is_static = true;
    /**
     * @var array|null list of patterns that specify which files/directories should NOT be processed.
     * If empty or not set, all files/directories will be processed.
     * See helpers/FileHelper::findFiles() description for pattern matching rules.
     * If a file/directory matches both a pattern in "only" and "except", it will NOT be processed.
     */
    public $configFile = [
        'attribute' => '@vendor/yunusbek/multilingual/src/config/attributes.php',
        'i18n' => '@vendor/yunusbek/multilingual/src/config/i18n.php'
    ];
    /**
     * @var array|null list of patterns that specify which files/directories should NOT be processed.
     * If empty or not set, all files/directories will be processed.
     * See helpers/FileHelper::findFiles() description for pattern matching rules.
     * If a file/directory matches both a pattern in "only" and "except", it will NOT be processed.
     */
    public $except = [
        '.*',
        '/.*',
        '/messages',
        '/tests',
        '/runtime',
        '/vendor',
        '/BaseYii.php', // contains examples about Yii::t()
    ];
    /**
     * @var array|null list of patterns that specify which files (not directories) should be processed.
     * If empty or not set, all files will be processed.
     * See helpers/FileHelper::findFiles() description for pattern matching rules.
     * If a file/directory matches both a pattern in "only" and "except", it will NOT be processed.
     */
    public $only = ['*.php'];
    /**
     * @var string connection component ID for "db" format.
     */
    public $db = 'db';
    /**
     * @var string File header in generated PHP file with messages. This property is used only if [[$format]] is "php".
     * @since 2.0.13
     */
    public $phpFileHeader = '';

    /**
     * @var array Config for messages extraction.
     * @see actionExtract()
     * @see initConfig()
     * @since 2.0.13
     */
    protected $config;

    public function __construct($id, $module, $config = [])
    {
        $filePath = $this->json_file_name.'.json';
        $this->languages = array_unique(array_merge(array_keys(Yii::$app->params['language_list']), array_column((new Query())->select(['key'])->from('{{%language_list}}')->all(), 'key')));
        if (file_exists($filePath)) {
            $jsonContent = file_get_contents($filePath);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->jsonData = json_decode($jsonContent, true);
            } else {
                $this->jsonError = true;
            }
        } else {
            $this->jsonData = [
                'where' => [],
                'tables' => [],
            ];
        }

        parent::__construct($id, $module, $config);
    }


    /**
     * {@inheritdoc}
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'sourcePath',
            'messagePath',
            'languages',
            'translator',
            'sort',
            'json_file_name',
            'overwrite',
            'is_static',
            'except',
            'only',
            'db',
            'phpFileHeader',
        ]);
    }

    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'e' => 'except',
            'i' => 'is_static',
            'l' => 'languages',
            'p' => 'messagePath',
            'o' => 'only',
            'w' => 'overwrite',
            'S' => 'sort',
            't' => 'translator',
            's' => 'sourcePath',
            'j' => 'json_file_name',
        ]);
    }

    /**
     * Creates a configuration file for the "extract" command using command line options specified.
     *
     * The generated configuration file contains parameters required
     * for source code messages extraction.
     * You may use this configuration file with the "extract" command.
     *
     * @param string $filePath output file name or alias.
     * @return int CLI exit code
     * @throws Exception|\yii\base\Exception on failure.
     */
    public function actionConfig(string $filePath): int
    {
        $filePath = Yii::getAlias($filePath);
        $dir = dirname($filePath);

        if (file_exists($filePath)) {
            if (!$this->confirm("File '{$filePath}' already exists. Do you wish to overwrite it?")) {
                return ExitCode::OK;
            }
        }

        $array = VarDumper::export($this->getOptionValues($this->action->id));
        $content = <<<EOD
<?php
/**
 * Configuration file for 'yii {$this->id}/{$this->defaultAction}' command.
 *
 * This file is automatically generated by 'yii {$this->id}/{$this->action->id}' command.
 * It contains parameters for source code messages extraction.
 * You may modify this file to suit your needs.
 *
 * You can use 'yii {$this->id}/{$this->action->id}-template' command to create
 * template configuration file with detailed description for each parameter.
 */
return $array;

EOD;

        if (FileHelper::createDirectory($dir) === false || file_put_contents($filePath, $content, LOCK_EX) === false) {
            $this->stdout("Configuration file was NOT created: '{$filePath}'.\n\n", BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Configuration file created: '{$filePath}'.\n\n", BaseConsole::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Creates a configuration file template for the "extract" command.
     *
     * The created configuration file contains detailed instructions on
     * how to customize it to fit for your needs. After customization,
     * you may use this configuration file with the "extract" command.
     *
     * @param string $filePath output file name or alias.
     * @return int CLI exit code
     */
    public function actionConfigTemplate(string $filePath): int
    {
        $filePath = Yii::getAlias($filePath);

        if (file_exists($filePath)) {
            if (!$this->confirm("File '{$filePath}' already exists. Do you wish to overwrite it?")) {
                return ExitCode::OK;
            }
        }

        if (!copy(Yii::getAlias('@yii/views/messageConfig.php'), $filePath)) {
            $this->stdout("Configuration file template was NOT created at '{$filePath}'.\n\n", BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Configuration file template created at '{$filePath}'.\n\n", BaseConsole::FG_GREEN);
        return ExitCode::OK;
    }

    public function getDefaultAction()
    {
        return 'extract';
    }

    /**
     * Extracts messages to be translated from source code.
     *
     * This command will search through source code files and extract
     * messages that need to be translated in different languages.
     *
     * You may use the "yii message/config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception|InvalidConfigException on failure.
     * @throws \yii\db\Exception
     */
    public function actionI18n(): void
    {
        $this->initConfig($this->configFile['i18n']);

        if (is_array($this->config['sourcePath'])) {
            $files = array_merge(...array_map(function ($path) {
                return FileHelper::findFiles(realpath($path), $this->config);
            }, $this->config['sourcePath']));

            $messages = [];
            array_walk($files, function ($file) use (&$messages) {
                $messages = array_merge_recursive($messages, $this->extractMessages($file, $this->config['translator']));
            });
        } else {
            $files = FileHelper::findFiles(realpath($this->config['sourcePath']), $this->config);
            $messages = [];
            foreach ($files as $file) {
                $messages = array_merge_recursive($messages, $this->extractMessages($file, $this->config['translator']));
            }
        }

        /** @var Connection $db */
        $db = Instance::ensure($this->config['db'], Connection::class);

        foreach ($this->languages as $language) {
            $langTable = "{{%lang_$language}}";
            if (!$this->issetTable($langTable)) {
                BaseLanguageQuery::createLangTable($langTable);
            }
            $this->saveMessagesToDb(
                $messages,
                $db,
                $langTable
            );
        }
    }

    /**
     * Extracts messages to be translated from source code.
     *
     * This command will search through source code files and extract
     * messages that need to be translated in different languages.
     *
     * You may use the "yii message/config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception|InvalidConfigException on failure.
     */
    public function actionAttributes(): void
    {
        $this->initConfig($this->configFile['attribute']);

        $files = FileHelper::findFiles(realpath($this->config['sourcePath']), $this->config);

        /** @var Connection $db */
        $db = Instance::ensure($this->config['db'], Connection::className());

        $attributes = [];
        foreach ($files as $file) {
            $extracted = $this->extractMessages($file, $this->config['translator']);
            $attributes = array_merge_recursive($attributes, $extracted);
        }

        $this->fixAttributesToDb(
            $attributes,
            $db
        );
    }

    /**
     * Saves messages to database.
     *
     * @param array $messages
     * @param Connection $db
     * @param string $langTable
     */
    protected function saveMessagesToDb(array $messages, Connection $db, string $langTable): void
    {
        $currentValues = [];
        $langTablesData = (new Query())->select(['is_static', 'table_name', 'table_iteration', 'value'])->where(['is_static' => true])->from($langTable)->all($db);
        foreach ($langTablesData as $row) {
            foreach (json_decode($row['value'], true) as $key => $item) {
                $currentValues[$row['table_name']][$key] = $item;
            }
        }
        $this->stdout("\nInserting new messages to the ");
        $this->stdout('"'.$langTable.'" ', BaseConsole::FG_YELLOW);
        $this->stdout("table... ");

        $execute = false;
        $insertCount = [];
        $obsoleteCount = [];
        try {
            Yii::$app->cache->delete($langTable);

            /** delete obsolete categories */
            $obsoleteCategories = array_diff(array_keys($currentValues), array_keys($messages));
            foreach ($obsoleteCategories as $obsoleteCategory) {
                $obsoleteCategoryData = $db->createCommand()
                    ->delete($langTable, [
                        'is_static' => true,
                        'table_name' => $obsoleteCategory,
                    ])
                    ->execute();
                if ($obsoleteCategoryData > 0) {
                    $obsoleteCount[$obsoleteCategory] = count($currentValues[$obsoleteCategory]);
                    unset($currentValues[$obsoleteCategory]);
                }
            }


            $new = [];
            $obsolete = [];
            $fullyPrepared = [];
            foreach ($messages as $category => $msgs) {
                $msgs = array_unique($msgs);
                if (isset($currentValues[$category])) {
                    $currentKeys = array_keys($currentValues[$category]);
                    asort($msgs);
                    asort($currentKeys);
                    /** insert news messages */
                    $new[$category] = array_fill_keys(array_diff($msgs, $currentKeys), '');

                    /** delete obsolete messages */
                    $obsolete[$category] = array_diff($currentKeys, $msgs);
                    foreach ($obsolete[$category] as $obmsg) {
                        foreach ($this->jsonData['tables'] ?? [] as $table_name => $attributes) {
                            $table_name = str_replace('_', ' ', ucwords($table_name, '_'));
                            if ($table_name !== $obmsg) {
                                $table_name = BaseLanguageQuery::toPlural($table_name);
                            }
                            if ($table_name !== $obmsg) {
                                unset($currentValues[$category][$obmsg]);
                            }
                            $obsolete[$category] = array_diff($obsolete[$category], [$obmsg]);
                        }
                    }
                    $obsCount = count($obsolete[$category]);
                    if ($obsCount > 0) {
                        $obsoleteCount[$category] = $obsCount;
                    }
                } else {
                    /** insert news categories */
                    $new[$category] = array_fill_keys($msgs, '');
                }
                $insCount = count($new[$category]);
                if ($insCount > 0) {
                    $insertCount[$category] = $insCount;
                }

                /** save changes */
                $currentValues[$category] = array_merge($currentValues[$category] ?? [], $new[$category]);
                ksort($currentValues[$category]);
            }

            $execute = $this->attachTransTablesToMessage($langTable, $currentValues, $this->jsonData['tables'] ?? [], $insertCount);
            Yii::$app->cache->getOrSet($langTable, function () use ($currentValues)
            {
                return $currentValues;
            }, 3600 * 2);
        } catch (\yii\db\Exception $e) {
            $this->stderr("\n".$e->getMessage() . "\n");
        }
        if ((!empty($insertCount) || !empty($obsoleteCount)) && $execute) {
            foreach ($insertCount as $category => $count) {
                $this->stderr("\n{$count} items successfully inserted to ", BaseConsole::FG_GREEN);
                $this->stderr('"'.$category.'" ', BaseConsole::FG_YELLOW, BaseConsole::ITALIC);
                $this->stderr("categor.", BaseConsole::FG_GREEN);
            }
            foreach ($obsoleteCount as $category => $count) {
                $this->stderr("\n{$count} items successfully deleted from ", BaseConsole::FG_GREEN);
                $this->stderr('"'.$category.'" ', BaseConsole::FG_YELLOW, BaseConsole::ITALIC);
                $this->stderr("category.", BaseConsole::FG_GREEN);
            }
            $this->stdout("\n");
        } else {
            $this->stdout("Nothing to save.\n");
        }
    }

    /**
     * Fixes database table and attributes to json.
     *
     * @param array $translateTables
     * @param Connection $db
     */
    protected function fixAttributesToDb(array $translateTables, Connection $db): void
    {
        $filePath = $this->json_file_name.'.json';
        $this->stdout("Setting translatable attributes to the ");
        $this->stdout('"'.$filePath.'" ', BaseConsole::FG_YELLOW);
        $this->stdout("file... ");

        $execute = true;
        $attributeCount = [];
        try {
            if ($this->jsonError) {
                $this->stderr("\n".'The JSON ', BaseConsole::FG_YELLOW);
                $this->stderr('"'.$filePath.'" ', BaseConsole::FG_RED);
                $this->stderr("file is incorrect\n", BaseConsole::FG_YELLOW);
            }
            ksort($translateTables);
            foreach ($translateTables as $table_name => $attributes) {
                if (BaseLanguageList::isTableExists($table_name)) {
                    $schema = $db->getTableSchema($table_name);
                    $columns = $schema ? array_keys($schema->columns) : [];
                    if (in_array('id', $columns)) {
                        $different = array_diff($this->jsonData['tables'][$table_name] ?? [], $columns);
                        $flip_columns = array_flip($columns);
                        foreach (array_unique($attributes) as $attribute) {
                            if (!isset($flip_columns[$attribute])) {
                                $this->stderr("\n".'The column ', BaseConsole::FG_YELLOW);
                                $this->stderr('"'.$attribute.'" ', BaseConsole::FG_CYAN);
                                $this->stderr('does not exist in the table ', BaseConsole::FG_YELLOW);
                                $this->stderr('"'.$table_name.'"'."\n", BaseConsole::FG_RED);
                                break;
                            } elseif (!isset($this->jsonData['tables'][$table_name]) || !in_array($attribute, $this->jsonData['tables'][$table_name])) {
                                $attributeCount[$table_name] = ($attributeCount[$table_name] ?? 0) + 1;
                                $this->jsonData['tables'][$table_name][] = $attribute;
                            }
                        }

                        if (!empty($different)) {
                            foreach ($different as $key => $alien_attribute) {
                                $this->stderr("\n".'The column ', BaseConsole::FG_YELLOW);
                                $this->stderr('"'.$alien_attribute.'" ', BaseConsole::FG_RED);
                                $this->stderr('in the json file was not found in the table ', BaseConsole::FG_YELLOW);
                                $this->stderr('"'.$table_name.'" ', BaseConsole::FG_CYAN);
                                $this->stderr("and was deleted.\n", BaseConsole::FG_YELLOW);
                                unset($this->jsonData['tables'][$table_name][$key]);
                            }
                        }
                        $this->jsonData['tables'][$table_name] = array_values(array_unique($this->jsonData['tables'][$table_name]));
                    } else {
                        $this->stderr("\n".'The column ', BaseConsole::FG_YELLOW);
                        $this->stderr('"id" ', BaseConsole::FG_CYAN);
                        $this->stderr('does not exist in the table ', BaseConsole::FG_YELLOW);
                        $this->stderr('"'.$table_name.'"'."\n", BaseConsole::FG_RED);
                        break;
                    }
                } else {
                    $this->stderr("\n".'"'.$table_name.'"', BaseConsole::FG_RED);
                    $this->stderr(" table doesn't exist"."\n", BaseConsole::FG_YELLOW);
                    break;
                }
            }
            if (empty($this->jsonData['where'])) {
                $this->jsonData = ['where' => (object)[]] + $this->jsonData;
            }

            $jsonData = json_encode($this->jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            file_put_contents($filePath, $jsonData);
        } catch (\yii\db\Exception $e) {
            $execute = false;
            $this->stderr("\n".$e->getMessage(), BaseConsole::FG_RED);
        }
        if ($execute && count($attributeCount) > 0) {
            foreach ($attributeCount as $table_name => $attribute_count) {
                $this->stdout("\nSet ", BaseConsole::FG_GREEN);
                $this->stdout("$attribute_count ", BaseConsole::FG_YELLOW);
                $this->stdout("attributes from ", BaseConsole::FG_GREEN);
                $this->stdout("\"$table_name\" ", BaseConsole::FG_CYAN, BaseConsole::ITALIC);
                $this->stdout("table for translate successfully.", BaseConsole::FG_GREEN);
            }
        } else {
            $this->stdout("Nothing to save.");
        }
        $this->stdout("\n");
    }

    /**
     * Extracts messages from a file.
     *
     * @param string $fileName name of the file to extract messages from
     * @param string|array $translator name of the function used to translate messages
     * This parameter is available since version 2.0.4.
     * @return array
     */
    protected function extractMessages(string $fileName, string|array $translator): array
    {
        $subject = file_get_contents($fileName);
        $messages = [];
        $tokens = token_get_all($subject);
        if ($this->config['is_static']) {
            $this->stdout('Extracting messages from ');
            $this->stdout($fileName, BaseConsole::FG_CYAN);
            $this->stdout("...\n");
            foreach ((array) $translator as $currentTranslator) {
                $translatorTokens = token_get_all('<?php ' . $currentTranslator);
                array_shift($translatorTokens);
                $messages = array_merge_recursive($messages, $this->extractMessagesFromTokens($tokens, $translatorTokens));
            }
        } else {
            $this->stdout('Extracting attributes from ');
            $this->stdout($fileName, BaseConsole::FG_CYAN);
            $this->stdout("...\n");
            foreach ((array) $translator as $currentTranslator) {
                $translatorTokens = token_get_all('<?php ' . $currentTranslator);
                array_shift($translatorTokens);
                $extracted = $this->extractAttributesFromTokens($tokens, $translatorTokens);
                $messages = array_merge_recursive($messages, $extracted);
            }
        }

        $this->stdout("\n");

        return $messages;
    }

    /**
     * Extracts messages from a parsed PHP tokens list.
     * @param array $tokens tokens to be processed.
     * @param array $translatorTokens translator tokens.
     * @return array messages.
     */
    protected function extractMessagesFromTokens(array $tokens, array $translatorTokens): array
    {
        $messages = [];
        $translatorTokensCount = count($translatorTokens);
        $matchedTokensCount = 0;
        $buffer = [];
        $pendingParenthesisCount = 0;

        foreach ($tokens as $tokenIndex => $token) {
            // finding out translator call
            if ($matchedTokensCount < $translatorTokensCount) {
                if ($this->tokensEqualM($token, $translatorTokens[$matchedTokensCount])) {
                    $matchedTokensCount++;
                } else {
                    $matchedTokensCount = 0;
                }
            } elseif ($matchedTokensCount === $translatorTokensCount) {
                // translator found

                // end of function call
                if ($this->tokensEqualM(')', $token)) {
                    $pendingParenthesisCount--;

                    if ($pendingParenthesisCount === 0) {
                        // end of translator call or end of something that we can't extract
                        if (isset($buffer[0][0], $buffer[1], $buffer[2][0]) && $buffer[0][0] === T_CONSTANT_ENCAPSED_STRING && $buffer[1] === ',' && $buffer[2][0] === T_CONSTANT_ENCAPSED_STRING) {
                            // is valid call we can extract
                            $category = stripcslashes($buffer[0][1]);
                            $category = mb_substr($category, 1, -1);

                            $fullMessage = mb_substr($buffer[2][1], 1, -1);
                            $i = 3;
                            while ($i < count($buffer) - 1 && !is_array($buffer[$i]) && $buffer[$i] === '.') {
                                $fullMessage .= mb_substr($buffer[$i + 1][1], 1, -1);
                                $i += 2;
                            }

                            $message = stripcslashes($fullMessage);
                            $messages[$category][] = $message;

                            $nestedTokens = array_slice($buffer, 3);
                            if (count($nestedTokens) > $translatorTokensCount) {
                                // search for possible nested translator calls
                                $messages = array_merge_recursive($messages, $this->extractMessagesFromTokens($nestedTokens, $translatorTokens));
                            }
                        } else {
                            // invalid call or dynamic call we can't extract
                            $line = BaseConsole::ansiFormat($this->getLine($buffer), [BaseConsole::FG_CYAN]);
                            $skipping = BaseConsole::ansiFormat('Skipping line', [BaseConsole::FG_YELLOW]);
                            $this->stdout("$skipping $line. Make sure both category and message are static strings.\n");
                        }

                        // prepare for the next match
                        $matchedTokensCount = 0;
                        $pendingParenthesisCount = 0;
                        $buffer = [];
                    } else {
                        $buffer[] = $token;
                    }
                } elseif ($this->tokensEqualM('(', $token)) {
                    // count beginning of function call, skipping translator beginning

                    // If we are not yet inside the translator, make sure that it's beginning of the real translator.
                    // See https://github.com/yiisoft/yii2/issues/16828
                    if ($pendingParenthesisCount === 0) {
                        $previousTokenIndex = $tokenIndex - $matchedTokensCount - 1;
                        if (is_array($tokens[$previousTokenIndex])) {
                            $previousToken = $tokens[$previousTokenIndex][0];
                            if (in_array($previousToken, [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM], true)) {
                                $matchedTokensCount = 0;
                                continue;
                            }
                        }
                    }

                    if ($pendingParenthesisCount > 0) {
                        $buffer[] = $token;
                    }
                    $pendingParenthesisCount++;
                } elseif (isset($token[0]) && !in_array($token[0], [T_WHITESPACE, T_COMMENT])) {
                    // ignore comments and whitespaces
                    $buffer[] = $token;
                }
            }
        }

        return $messages;
    }

    /**
     * Extracts messages from a parsed PHP tokens list.
     * @param array $tokens tokens to be processed.
     * @return array messages.
     */
    protected function extractAttributesFromTokens(array $tokens): array
    {
        $attributes = [];
        $buffer = [];
        $inWidgetCall = false;
        $parenthesisCount = 0;

        foreach ($tokens as $token) {
            if (is_array($token) && ($token[0] === T_NAME_FULLY_QUALIFIED || $token[0] === T_STRING)) {
                switch ($token[1]) {
                    case '\Yunusbek\Multilingual\widgets\MultilingualAttributes':
                    case 'Multilingual\widgets\MultilingualAttributes':
                    case 'widgets\MultilingualAttributes':
                    case 'MultilingualAttributes':
                        $inWidgetCall = true;
                        break;
                }
            }

            if ($inWidgetCall) {
                if ($this->tokensEqualA('(', $token)) {
                    $parenthesisCount++;
                } elseif ($this->tokensEqualA(')', $token)) {
                    $parenthesisCount--;
                    if ($parenthesisCount === 0) {
                        $formBuffer = $this->parseAttributesFromBuffer($buffer);
                        $attributes = array_merge_recursive($attributes, $formBuffer);
                        $buffer = [];
                        $inWidgetCall = false;
                    }
                } elseif ($parenthesisCount > 0) {
                    $buffer[] = $token;
                }
            }
        }

        return $attributes;
    }

    /**
     * Attaches the names of tables to be translated to messages.
     * @param string $langTable
     * @param array $messages
     * @param array $json_list
     * @param array $insertCount
     * @return int
     * @throws \yii\db\Exception
     */
    protected function attachTransTablesToMessage(string $langTable, array &$messages, array $json_list, array &$insertCount): int
    {
        $execute = 1;
        $result_category = null;
        foreach (array_keys($json_list) as $item) {
            foreach ($messages as $category => $values) {
                if (in_array($item, $values, true)) {
                    $result_category = $category;
                    break;
                }
            }
        }
        if ($result_category === null) {
            $result_category = 'multilingual';
        }

        foreach ($messages as $category => $message) {
            foreach ($json_list as $table_name => $attributes) {
                if ($category === $result_category) {
                    $table_name = str_replace('_', ' ', ucwords($table_name, '_'));
                    if (!in_array($table_name, array_keys($message))) {
                        $table_name = BaseLanguageQuery::toPlural($table_name);
                    }
                    if (!in_array($table_name, array_keys($message)) && !isset($message[$table_name])) {
                        $message = array_merge($message, [$table_name => '']);
                        $insertCount[$category] = ($insertCount[$category] ?? 0) + 1;
                    }
                }
            }

            $execute = BaseLanguageQuery::upsert($langTable, $category, 0, true, $message);
            if (!$execute) {
                $this->stderr("\n".'"'.$langTable.'" '.json_encode($category)." failed\n", BaseConsole::FG_RED);
                break;
            }
        }

        return $execute;
    }

    protected function parseAttributesFromBuffer(array $buffer)
    {
        $attributes = [];
        $tableName = null;
        $isTableNameKey = false;
        $isAttributeKey = false;
        $isModelKey = false;
        $modelVar = null;

        for ($i = 0; $i < count($buffer); $i++) {
            // 'model' ni aniqlash
            if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING && trim($buffer[$i][1], "'\"") === 'model') {
                $isModelKey = true;
            } elseif ($isModelKey) {
                while ($i < count($buffer) && isset($buffer[$i][0]) && $buffer[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < count($buffer) && $this->tokensEqualA('=>', $buffer[$i])) {
                    $i++;
                    while ($i < count($buffer) && isset($buffer[$i][0]) && $buffer[$i][0] === T_WHITESPACE) {
                        $i++;
                    }
                    if (isset($buffer[$i][0]) && $buffer[$i][0] === T_VARIABLE) {
                        $modelVar = $buffer[$i][1];
                    }
                    $isModelKey = false;
                }
            }

            // 'table_name' ni aniqlash
            if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING && trim($buffer[$i][1], "'\"") === 'table_name') {
                $isTableNameKey = true;
            } elseif ($isTableNameKey) {
                while ($i < count($buffer) && isset($buffer[$i][0]) && $buffer[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < count($buffer) && $this->tokensEqualA('=>', $buffer[$i])) {
                    $i++;
                    while ($i < count($buffer) && isset($buffer[$i][0]) && $buffer[$i][0] === T_WHITESPACE) {
                        $i++;
                    }
                    if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $tableName = trim($buffer[$i][1], "'\"");
                    } elseif (isset($buffer[$i][0]) && $buffer[$i][0] === T_VARIABLE &&
                        isset($buffer[$i + 1][0]) && $buffer[$i + 1][0] === T_DOUBLE_COLON &&
                        isset($buffer[$i + 2][0]) && $buffer[$i + 2][0] === T_STRING && $buffer[$i + 2][1] === 'tableName') {
                        $tableName = $modelVar ? "$modelVar::tableName()" : 'dynamic_table_name';
                        $i += 3;
                    }
                    $isTableNameKey = false;
                }
            }

            // 'attribute' ni aniqlash
            if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING && trim($buffer[$i][1], "'\"") === 'attribute') {
                $isAttributeKey = true;
            } elseif ($isAttributeKey) {
                while ($i < count($buffer) && isset($buffer[$i][0]) && $buffer[$i][0] === T_WHITESPACE) {
                    $i++;
                }
                if ($i < count($buffer) && $this->tokensEqualA('=>', $buffer[$i])) {
                    $i++;
                    while ($i < count($buffer) && isset($buffer[$i][0]) && $buffer[$i][0] === T_WHITESPACE) {
                        $i++;
                    }
                    if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $value = trim($buffer[$i][1], "'\"");
                        if ($tableName) {
                            $attributes[$tableName][] = $value;
                        } else {
                            $this->stderr("Attribute ", BaseConsole::FG_YELLOW);
                            $this->stderr('"table_name" ', BaseConsole::FG_RED);
                            $this->stderr("not found in ", BaseConsole::FG_YELLOW);
                            $this->stderr("MultilingualAttributes::", BaseConsole::FG_GREY);
                            $this->stderr("widget", BaseConsole::FG_CYAN);
                            die($this->stderr("().\n", BaseConsole::FG_GREY));
                        }
                    } elseif ($this->tokensEqualA('[', $buffer[$i])) {
                        $i++;
                        while ($i < count($buffer) && !$this->tokensEqualA(']', $buffer[$i])) {
                            if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                                $value = trim($buffer[$i][1], "'\"");
                                if ($tableName) {
                                    $attributes[$tableName][] = $value;
                                } else {
                                    $this->stderr("Attribute ", BaseConsole::FG_YELLOW);
                                    $this->stderr('"table_name" ', BaseConsole::FG_RED);
                                    $this->stderr("not found in ", BaseConsole::FG_YELLOW);
                                    $this->stderr("MultilingualAttributes::", BaseConsole::FG_GREY);
                                    $this->stderr("widget", BaseConsole::FG_CYAN);
                                    die($this->stderr("().\n", BaseConsole::FG_GREY));
                                }
                            }
                            $i++;
                        }
                    }
                    $isAttributeKey = false;
                }
            }
        }

        return $attributes;
    }

    protected function tokensEqualA($expected, $token): bool
    {
        if ($expected === '=>' && is_array($token) && $token[0] === T_DOUBLE_ARROW) {
            return true;
        }
        return (!is_array($token) && $token === $expected) ||
            (is_array($token) && $token[1] === $expected);
    }

    /**
     * Finds out if two PHP tokens are equal.
     *
     * @param array|string $a
     * @param array|string $b
     * @return bool
     * @since 2.0.1
     */
    protected function tokensEqualM($a, $b): bool
    {
        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }
        if (isset($a[0], $a[1], $b[0], $b[1])) {
            return $a[0] === $b[0] && $a[1] == $b[1];
        }

        return false;
    }

    protected function findByTableIteration($array, $iteration) {
        foreach ($array as $item) {
            if ($item['table_iteration'] === $iteration) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Finds out a line of the first non-char PHP token found.
     *
     * @param array $tokens
     * @return int|string
     * @since 2.0.1
     */
    protected function getLine($tokens)
    {
        foreach ($tokens as $token) {
            if (isset($token[2])) {
                return $token[2];
            }
        }

        return 'unknown';
    }

    /**
     * @param string $configFile
     * @throws Exception If configuration file does not exists.
     * @since 2.0.13
     */
    protected function initConfig($configFile)
    {
        $configFileContent = [];
        if ($configFile !== null) {
            $configFile = Yii::getAlias($configFile);
            if (!is_file($configFile)) {
                throw new Exception("The configuration file does not exist: $configFile");
            }
            $configFileContent = require $configFile;
        }

        $this->config = array_merge(
            $this->getOptionValues($this->action->id),
            $configFileContent,
            $this->getPassedOptionValues()
        );
        if (is_array($this->config['sourcePath'])){
            foreach ($this->config['sourcePath'] as $key => $sourcePath) {
                $this->config['sourcePath'][$key] = Yii::getAlias($sourcePath);
                if (!isset($this->config['sourcePath'][$key])) {
                    throw new Exception('The configuration file must specify "sourcePath".');
                }
                if (!is_dir($this->config['sourcePath'][$key])) {
                    throw new Exception("The source path {$this->config['sourcePath'][$key]} is not a valid directory.");
                }
            }
        } else {
            $this->config['sourcePath'] = Yii::getAlias($this->config['sourcePath']);
            if (!isset($this->config['sourcePath'])) {
                throw new Exception('The configuration file must specify "sourcePath".');
            }
            if (!is_dir($this->config['sourcePath'])) {
                throw new Exception("The source path {$this->config['sourcePath']} is not a valid directory.");
            }
        }
    }

    /** This checks if the table exists in the database
     * @throws \yii\db\Exception
     */
    private function issetTable(string $table_name): bool
    {
        $table_name = Yii::$app->db->schema->getRawTableName($table_name);
        return Yii::$app->db->createCommand("SELECT to_regclass(:table) IS NOT NULL")->bindValue(':table', $table_name)->queryScalar();
    }
}
