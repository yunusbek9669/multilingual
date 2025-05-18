<?php

namespace Yunusbek\Multilingual\commands;

use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;
use yii\helpers\FileHelper;

class Migrations extends Controller
{
    public $defaultAction = 'generate';

    /**
     * @throws Exception
     */
    public function actionGenerate()
    {
        $migrationClassName = 'm' . gmdate('ymd_His') . '_create_language_list_table';

        $migrationCode = <<<PHP
<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%language_list}}`.
 */
class {$migrationClassName} extends Migration
{
    public function safeUp()
    {
        \$this->createTable('{{%language_list}}', [
            'id' => \$this->primaryKey(),
            'name' => \$this->string(30)->notNull(), # Type the full name of the current language.
            'short_name' => \$this->string(5)->notNull(), # Type a short name for the current language.
            'key' => \$this->string(5)->notNull(), # Enter the short code representing the current language. Based on the international standard ISO 639-1 language codes. For example: uz, en, ru.
            'image' => \$this->string(50), # Enter the path to the flag image for the current language.
            'import_excel' => \$this->string(50), # Import an Excel file downloaded from an existing language and translated into a new language
            'table' => \$this->string(50)->notNull(), # The name of the table where translations for the current language are stored, for example: lang_uz, lang_en, lang_ru. (rule: do not deviate from the standard lang_* pattern, do not enter it manually)
        ]);

        \$this->createIndex('{{%idx-language_list-key}}', '{{%language_list}}', 'key');
    }

    public function safeDown()
    {
        \$this->dropIndex('{{%idx-language_list-key}}', '{{%language_list}}');
        \$this->dropTable('{{%language_list}}');
    }
}
PHP;

        $dir = Yii::getAlias('@app/migrations');

        $filePath = $dir . '/' . $migrationClassName . '.php';

        if (FileHelper::createDirectory($dir) === false || file_put_contents($filePath, $migrationCode, LOCK_EX) === false) {
            $this->stdout("An error occurred while creating the migration file!\n\n", BaseConsole::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "Migration file created successfully. {$filePath}\n";
        return ExitCode::OK;
    }
}