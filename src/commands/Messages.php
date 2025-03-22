<?php

namespace Yunusbek\Multilingual\commands;

use Yii;
use yii\console\Exception;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\VarDumper;
use yii\i18n\GettextPoFile;
use Yunusbek\Multilingual\models\BaseLanguageList;

/**
 * Extracts messages to be translated from source files.
 *
 * The extracted messages can be saved the following depending on `format`
 * setting in config file:
 *
 * - PHP message source files.
 * - ".po" files.
 * - Database.
 *
 * Usage:
 * 1. Create a configuration file using the 'message/config' command:
 *    yii message/config /path/to/myapp/messages/config.php
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
     * @var bool whether to remove messages that no longer appear in the source code.
     * Defaults to false, which means these messages will NOT be removed.
     */
    public $removeUnused = false;
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
     * @var string generated file format. Can be "php", "db", "po" or "pot".
     */
    public $format = 'php';
    /**
     * @var string connection component ID for "db" format.
     */
    public $db = 'db';
    /**
     * @var string custom name for source message table for "db" format.
     */
    public $langTable = '{{%lang_}}';
    /**
     * @var string File header in generated PHP file with messages. This property is used only if [[$format]] is "php".
     * @since 2.0.13
     */
    public $phpFileHeader = '';
    /**
     * @var string|null DocBlock used for messages array in generated PHP file. If `null`, default DocBlock will be used.
     * This property is used only if [[$format]] is "php".
     * @since 2.0.13
     */
    public $phpDocBlock;

    /**
     * @var array Config for messages extraction.
     * @see actionExtract()
     * @see initConfig()
     * @since 2.0.13
     */
    protected $config;


    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'sourcePath',
            'messagePath',
            'languages',
            'translator',
            'sort',
            'overwrite',
            'is_static',
            'removeUnused',
            'except',
            'only',
            'format',
            'db',
            'langTable',
            'phpFileHeader',
            'phpDocBlock',
        ]);
    }

    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'e' => 'except',
            'f' => 'format',
            'i' => 'is_static',
            'l' => 'languages',
            'p' => 'messagePath',
            'o' => 'only',
            'w' => 'overwrite',
            'S' => 'sort',
            't' => 'translator',
            'm' => 'langTable',
            's' => 'sourcePath',
            'r' => 'removeUnused',
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
     * @throws Exception on failure.
     */
    public function actionConfig($filePath)
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
            $this->stdout("Configuration file was NOT created: '{$filePath}'.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Configuration file created: '{$filePath}'.\n\n", Console::FG_GREEN);
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
     * @throws Exception on failure.
     */
    public function actionConfigTemplate($filePath)
    {
        $filePath = Yii::getAlias($filePath);

        if (file_exists($filePath)) {
            if (!$this->confirm("File '{$filePath}' already exists. Do you wish to overwrite it?")) {
                return ExitCode::OK;
            }
        }

        if (!copy(Yii::getAlias('@yii/views/messageConfig.php'), $filePath)) {
            $this->stdout("Configuration file template was NOT created at '{$filePath}'.\n\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Configuration file template created at '{$filePath}'.\n\n", Console::FG_GREEN);
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
     * @param string|null $configFile the path or alias of the configuration file.
     * You may use the "yii message/config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception on failure.
     */
    public function actionI18n($configFile = '@vendor/yunusbek/multilingual/src/config/i18n.php')
    {
        $this->initConfig($configFile);

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
        $db = Instance::ensure($this->config['db'], Connection::className());

        foreach ($this->config['languages'] as $language) {
            $langTable = "{{%lang_$language}}";
            $this->saveMessagesToDb(
                $messages,
                $db,
                $langTable,
                $this->config['removeUnused']
            );
        }
    }

    /**
     * Extracts messages to be translated from source code.
     *
     * This command will search through source code files and extract
     * messages that need to be translated in different languages.
     *
     * @param string|null $configFile the path or alias of the configuration file.
     * You may use the "yii message/config" command to generate
     * this file and then customize it for your needs.
     * @throws Exception on failure.
     */
    public function actionAttributes($configFile = '@vendor/yunusbek/multilingual/src/config/attributes.php')
    {
        $this->initConfig($configFile);

        $files = FileHelper::findFiles(realpath($this->config['sourcePath']), $this->config);

        /** @var Connection $db */
        $db = Instance::ensure($this->config['db'], Connection::className());

        $attributes = [];
        foreach ($files as $file) {
            $extracted = $this->extractMessages($file, $this->config['translator']);
            $attributes = array_merge_recursive($attributes, $extracted);
        }

        foreach ($this->config['languages'] as $language) {
            $langTable = "{{%lang_$language}}";
            $this->saveAttributesToDb(
                $attributes,
                $db,
                $langTable,
                $this->config['removeUnused']
            );
        }
    }

    /**
     * Saves messages to database.
     *
     * @param array $messages
     * @param Connection $db
     * @param string $langTable
     * @param bool $removeUnused
     */
    protected function saveMessagesToDb($messages, $db, $langTable, $removeUnused)
    {
        $currentValues = [];
        $currentMessages = [];
        $langTbalesData = (new Query())->select(['is_static', 'table_name', 'table_iteration', 'value'])->where(['is_static' => true])->from($langTable)->all($db);
        foreach ($langTbalesData as $row) {
            foreach (json_decode($row['value']) as $key => $item) {
                $currentMessages[$row['table_name']][] = $key;
                $currentValues[$row['table_name']][$key] = $item;
            }
        }

        $this->stdout("\nInserting new messages to the ");
        $this->stdout('"'.$langTable.'" ', Console::FG_YELLOW);
        $this->stdout("table... ");

        $execute = false;
        try {
            Yii::$app->cache->delete($langTable);

            /** delete obsolete categories */
            $obsoleteCount = [];
            $obsoleteCategories = array_diff(array_keys($currentMessages), array_keys($messages));
            foreach ($obsoleteCategories as $obsoleteCategory) {
                $obsoleteCategoryData = $db->createCommand()
                    ->delete($langTable, [
                        'is_static' => true,
                        'table_name' => $obsoleteCategory,
                    ])
                    ->execute();
                if ($obsoleteCategoryData > 0) {
                    $obsoleteCount[$obsoleteCategory] = count($currentMessages[$obsoleteCategory]);
                    unset($currentMessages[$obsoleteCategory]);
                }
            }


            $new = [];
            $obsolete = [];
            $insertCount = [];
            $obsaCount = [];
            foreach ($messages as $category => $msgs) {
                $msgs = array_unique($msgs);
                if (isset($currentMessages[$category])) {
                    /** insert news messages */
                    $new[$category] = array_fill_keys(array_diff($msgs, $currentMessages[$category]), '');

                    /** delete obsolete messages */
                    $obsolete[$category] = array_diff($currentMessages[$category], $msgs);
                    $obsCount = count($obsolete[$category]);
                    if ($obsCount > 0) {
                        $obsoleteCount[$category] = $obsCount;
                    }
                    foreach ($obsolete[$category] as $obmsg) {
                        unset($currentValues[$category][$obmsg]);
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
                $values = array_merge($currentValues[$category] ?? [], $new[$category]);
                $execute = $db->createCommand()
                    ->upsert($langTable, [
                        'is_static' => true,
                        'table_name' => $category,
                        'table_iteration' => 0,
                        'value' => $values,
                    ], [
                        'value' => $values
                    ])->execute();
                if (!$execute) {
                    $this->stderr("\n".'"'.$langTable.'" '.json_encode($values)." failed\n", Console::FG_RED);
                    break;
                }
            }
            Yii::$app->cache->getOrSet($langTable, function () use ($currentValues)
            {
                return $currentValues;
            }, 3600 * 2);
        } catch (\yii\db\Exception $e) {
            $this->stderr("\n".$e->getMessage() . "\n");
        }
        if (($insertCount || $obsoleteCount) && $execute) {
            foreach ($insertCount as $category => $count) {
                $this->stderr("\n{$count} items successfully inserted to ", Console::FG_GREEN);
                $this->stderr('"'.$category.'" ', Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("categor.", Console::FG_GREEN);
            }
            foreach ($obsoleteCount as $category => $count) {
                $this->stderr("\n{$count} items successfully deleted from ", Console::FG_GREEN);
                $this->stderr('"'.$category.'" ', Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("category.", Console::FG_GREEN);
            }
            $this->stdout("\n");
        } else {
            $this->stdout("Nothing to save.\n");
        }
    }

    /**
     * Saves messages to database.
     *
     * @param array $messages
     * @param Connection $db
     * @param string $langTable
     * @param bool $removeUnused
     */
    protected function saveAttributesToDb($translateTables, $db, $langTable, $removeUnused)
    {
        $this->stdout("Inserting new attributes to the ");
        $this->stdout('"'.$langTable.'" ', Console::FG_YELLOW);
        $this->stdout("table...\n");

        $insertCount = [];
        $updateColumnCount = ['add' => [], 'delete' => []];
        $deleteCount = [];
        $obsoleteCount = [];
        $execute = true;
        try {
            foreach ($translateTables as $table_name => $attributes) {
                $obsoleteLangTableData = (new Query())->select(['is_static', 'table_name', 'table_iteration', 'value'])->where(['is_static' => false, 'table_name' => $table_name])->from($langTable)->all($db);
                if (BaseLanguageList::isTableExists($table_name)) {
                    $schema = $db->getTableSchema($table_name);
                    $columns = $schema ? array_keys($schema->columns) : [];

                    if (in_array('id', $columns)) {
                        $translateTable = (new Query())->select(array_merge(['id'], $attributes))->from($table_name)->all($db);
                        if (!empty($translateTable)) {
                            $insCount = 0;
                            $addColumnCount = 0;
                            $deleteColumnCount = 0;
                            foreach ($translateTable as $key => $row) {
                                if (empty($this->findByTableIteration($obsoleteLangTableData, $row['id']))) {
                                    $values = array_fill_keys($attributes, '');
                                    $execute = $db->createCommand()->insert($langTable, ['is_static' => false, 'table_name' => $table_name, 'table_iteration' => $row['id'], 'value' => $values])->execute();
                                    if (!$execute) {
                                        $this->stderr('"'.$langTable.'" '.json_encode($values)." failed\n", Console::FG_RED);
                                        break 2;
                                    } else {
                                        $insCount++;
                                    }
                                } else {
                                    $currentLangValues = [];
                                    if (isset($obsoleteLangTableData[$key])) { $currentLangValues = json_decode($obsoleteLangTableData[$key]['value'], true); }
                                    if (array_keys($currentLangValues) !== $attributes) {
                                        $langValues = array_merge(array_fill_keys($attributes, null), $currentLangValues);
                                        $langValues = array_intersect_key($langValues, array_flip($attributes));
                                        $execute = $db->createCommand()->upsert($langTable, ['is_static' => false, 'table_name' => $table_name, 'table_iteration' => $row['id'], 'value' => $langValues], ['value' => $langValues])->execute();
                                        if (!$execute) {
                                            $this->stderr('"'.$langTable.'" '.json_encode($langValues)." failed\n", Console::FG_RED);
                                            break 2;
                                        } else {
                                            if (count($attributes) > count($currentLangValues)) {
                                                $addColumnCount++;
                                            } else {
                                                $deleteColumnCount++;
                                            }
                                        }
                                    }
                                }
                                if ($insCount > 0) { $insertCount[$table_name] = $insCount; }
                                if ($addColumnCount > 0) { $updateColumnCount['add'][$table_name] = $addColumnCount; }
                                if ($deleteColumnCount > 0) { $updateColumnCount['delete'][$table_name] = $deleteColumnCount; }
                            }
                        } else {
                            $affectedRows = $db->createCommand()
                                ->delete($langTable, [
                                    'is_static' => false,
                                    'table_name' => $table_name,
                                ])
                                ->execute();
                            if ($affectedRows > 0) {
                                $deleteCount[$table_name] = $affectedRows;
                            }
                        }
                    } else {
                        $this->stderr("\n".'The column ', Console::FG_YELLOW);
                        $this->stderr('"id" ', Console::FG_CYAN);
                        $this->stderr('does not exist in the table ', Console::FG_YELLOW);
                        $this->stderr('"'.$table_name.'"'."\n", Console::FG_RED);
                        break;
                    }
                } else {
                    $this->stderr("\n".'"'.$table_name.'"', Console::FG_RED);
                    $this->stderr(" table doesn't exist"."\n", Console::FG_YELLOW);
                    break;
                }
            }
            $obsoleteTableNames = (new Query())->select(['table_name'])->distinct()->where(['is_static' => false])->andWhere(['not in', 'table_name', array_keys($translateTables)])->from($langTable)->column($db);
            foreach ($obsoleteTableNames as $tableName) {
                $obsoleteLangTableData = $db->createCommand()
                    ->delete($langTable, [
                        'is_static' => false,
                        'table_name' => $tableName,
                    ])
                    ->execute();
                if ($obsoleteLangTableData > 0) {
                    $obsoleteCount[$tableName] = $obsoleteLangTableData;
                }
            }
        } catch (\yii\db\Exception $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);
        }
        if ((!empty($insertCount) || !empty($updateColumnCount)) && $execute) {
            foreach ($insertCount as $table_name => $count) {
                $this->stderr("Inserted $count rows to ", Console::FG_GREEN);
                $this->stderr("\"$table_name\" ", Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("category successfully.\n", Console::FG_GREEN);
            }
            foreach ($updateColumnCount['add'] as $table_name => $count) {
                $this->stderr("Added $count items to ", Console::FG_GREEN);
                $this->stderr("\"value\" ", Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("attribute from ", Console::FG_GREEN);
                $this->stderr("\"$table_name\" ", Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("category successfully.\n", Console::FG_GREEN);
            }
            foreach ($updateColumnCount['delete'] as $table_name => $count) {
                $this->stderr("Deleted $count items to ", Console::FG_GREEN);
                $this->stderr("\"value\" ", Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("attribute from ", Console::FG_GREEN);
                $this->stderr("\"$table_name\" ", Console::FG_YELLOW, Console::ITALIC);
                $this->stderr("category successfully.\n", Console::FG_GREEN);
            }
        } else {
            $this->stdout("Nothing to save.\n");
        }
        if (!empty($deleteCount)) {
            foreach ($deleteCount as $table_name => $item) {
                $this->stderr("Deleted rows for ", Console::FG_YELLOW);
                $this->stderr("\"$table_name\"", Console::FG_CYAN, Console::ITALIC);
                $this->stderr(" category: $item.\n", Console::FG_YELLOW);
            }
        }
        if (!empty($obsoleteCount)) {
            foreach ($obsoleteCount as $table_name => $item) {
                $this->stderr("Deleted obsolete ", Console::FG_YELLOW);
                $this->stderr("\"$table_name\"", Console::FG_CYAN, Console::ITALIC);
                $this->stderr(" category with $item rows.\n", Console::FG_YELLOW);
            }
        }
    }

    /**
     * Extracts messages from a file.
     *
     * @param string $fileName name of the file to extract messages from
     * @param string $translator name of the function used to translate messages
     * This parameter is available since version 2.0.4.
     * @return array
     */
    protected function extractMessages($fileName, $translator)
    {
        $subject = file_get_contents($fileName);
        $messages = [];
        $tokens = token_get_all($subject);
        if ($this->config['is_static']) {
            $this->stdout('Extracting messages from ');
            $this->stdout($fileName, Console::FG_CYAN);
            $this->stdout("...\n");
            foreach ((array) $translator as $currentTranslator) {
                $translatorTokens = token_get_all('<?php ' . $currentTranslator);
                array_shift($translatorTokens);
                $messages = array_merge_recursive($messages, $this->extractMessagesFromTokens($tokens, $translatorTokens));
            }
        } else {
            $this->stdout('Extracting attributes from ');
            $this->stdout($fileName, Console::FG_CYAN);
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
    protected function extractMessagesFromTokens(array $tokens, array $translatorTokens)
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
                            $line = Console::ansiFormat($this->getLine($buffer), [Console::FG_CYAN]);
                            $skipping = Console::ansiFormat('Skipping line', [Console::FG_YELLOW]);
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
     * @param array $translatorTokens translator tokens.
     * @return array messages.
     */
    protected function extractAttributesFromTokens(array $tokens, array $translatorTokens)
    {
        $attributes = [];
        $buffer = [];
        $inWidgetCall = false;
        $parenthesisCount = 0;

        foreach ($tokens as $token) {
            if (is_array($token) && ($token[0] === T_NAME_FULLY_QUALIFIED || $token[0] === T_STRING)) {
                switch ($token[1]) {
                    case '\Yunusbek\Multilingual\widgets\MultilingualAttributes':
                        $inWidgetCall = true;
                        break;
                    case 'Multilingual\widgets\MultilingualAttributes':
                        $inWidgetCall = true;
                        break;
                    case 'widgets\MultilingualAttributes':
                        $inWidgetCall = true;
                        break;
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
                            $this->stderr("Attribute ", Console::FG_YELLOW);
                            $this->stderr('"table_name" ', Console::FG_RED);
                            $this->stderr("not found in ", Console::FG_YELLOW);
                            $this->stderr("MultilingualAttributes::", Console::FG_GREY);
                            $this->stderr("widget", Console::FG_CYAN);
                            die($this->stderr("().\n", Console::FG_GREY));
                        }
                    } elseif ($this->tokensEqualA('[', $buffer[$i])) {
                        $i++;
                        while ($i < count($buffer) && !$this->tokensEqualA(']', $buffer[$i])) {
                            if (isset($buffer[$i][0]) && $buffer[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
                                $value = trim($buffer[$i][1], "'\"");
                                if ($tableName) {
                                    $attributes[$tableName][] = $value;
                                } else {
                                    $this->stderr("Attribute ", Console::FG_YELLOW);
                                    $this->stderr('"table_name" ', Console::FG_RED);
                                    $this->stderr("not found in ", Console::FG_YELLOW);
                                    $this->stderr("MultilingualAttributes::", Console::FG_GREY);
                                    $this->stderr("widget", Console::FG_CYAN);
                                    die($this->stderr("().\n", Console::FG_GREY));
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

    protected function tokensEqualA($expected, $token)
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
    protected function tokensEqualM($a, $b)
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
                if (!isset($this->config['sourcePath'][$key], $this->config['languages'])) {
                    throw new Exception('The configuration file must specify "sourcePath" and "languages".');
                }
                if (!is_dir($this->config['sourcePath'][$key])) {
                    throw new Exception("The source path {$this->config['sourcePath'][$key]} is not a valid directory.");
                }
            }
        } else {
            $this->config['sourcePath'] = Yii::getAlias($this->config['sourcePath']);
            if (!isset($this->config['sourcePath'], $this->config['languages'])) {
                throw new Exception('The configuration file must specify "sourcePath" and "languages".');
            }
            if (!is_dir($this->config['sourcePath'])) {
                throw new Exception("The source path {$this->config['sourcePath']} is not a valid directory.");
            }
        }
        $this->config['messagePath'] = Yii::getAlias($this->config['messagePath']);
        if (empty($this->config['format']) || !in_array($this->config['format'], ['php', 'po', 'pot', 'db'])) {
            throw new Exception('Format should be either "php", "po", "pot" or "db".');
        }
        if (in_array($this->config['format'], ['php', 'po', 'pot'])) {
            if (!isset($this->config['messagePath'])) {
                throw new Exception('The configuration file must specify "messagePath".');
            }
            if (!is_dir($this->config['messagePath'])) {
                throw new Exception("The message path {$this->config['messagePath']} is not a valid directory.");
            }
        }
        if (empty($this->config['languages'])) {
            throw new Exception('Languages cannot be empty.');
        }

        if ($this->config['format'] === 'php' && $this->config['phpDocBlock'] === null) {
            $this->config['phpDocBlock'] = <<<DOCBLOCK
/**
 * Message translations.
 *
 * This file is automatically generated by 'yii {$this->id}/{$this->action->id}' command.
 * It contains the localizable messages extracted from source code.
 * You may modify this file by translating the extracted messages.
 *
 * Each array element represents the translation (value) of a message (key).
 * If the value is empty, the message is considered as not translated.
 * Messages that no longer need translation will have their translations
 * enclosed between a pair of '@@' marks.
 *
 * Message string can be used with plural forms format. Check i18n section
 * of the guide for details.
 *
 * NOTE: this file must be saved in UTF-8 encoding.
 */
DOCBLOCK;
        }
    }
}
