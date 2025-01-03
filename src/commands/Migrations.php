<?php

namespace Yunusbek\Multilingual\commands;

use Yii;
use yii\console\Controller;

class Migrations extends Controller
{
    public function actionGenerate()
    {
        $migrationClassName = 'm' . gmdate('ymd_His') . '_create_language_list_table';

        $dir = Yii::getAlias('@app/migrations');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . '/' . $migrationClassName . '.php';

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
            'name' => \$this->string(30)->notNull(),
            'short_name' => \$this->string(5)->notNull(),
            'key' => \$this->string(5)->notNull(),
            'image' => \$this->string(50),
            'import_excel' => \$this->string(50),
            'table' => \$this->string(50)->notNull(),
            'order_number' => \$this->integer(),
            'status' => \$this->integer(2),
            'created_at' => \$this->bigInteger(),
            'created_by' => \$this->integer(),
            'updated_at' => \$this->bigInteger(),
            'updated_by' => \$this->integer(),
        ]);

        \$this->createIndex('{{%idx-language_list-status}}', '{{%language_list}}', 'status');
        \$this->createIndex('{{%idx-language_list-created_by}}', '{{%language_list}}', 'created_by');
        \$this->createIndex('{{%idx-language_list-updated_by}}', '{{%language_list}}', 'updated_by');
        
        \$this->upsert('language_list', [
            'id' => 1,
            'name' => null, # Type the full name of the current language.
            'short_name' => null, # Type a short name for the current language.
            'key' => null, # Enter the short code representing the current language. Based on the international standard ISO 639-1 language codes. For example: uz, en, ru.
            'image' => null, # Enter the path to the flag image for the current language.
            'import_excel' => null, # Should be empty for now
            'table' => null, # Enter the name of the table that stores translations for the current language, for example: lang_uz, lang_en, lang_ru. (rule: do not deviate from the standard lang_* pattern)
            'order_number' => 1,
            'status' => 1,
            'created_by' => 1,
            'created_at' => time(),
            'updated_by' => 1,
            'updated_at' => time()
        ]);
        \$this->execute("SELECT setval('language_list_id_seq', max(id)) FROM language_list");
    }

    public function safeDown()
    {
        \$this->dropIndex('{{%idx-language_list-status}}', '{{%language_list}}');
        \$this->dropIndex('{{%idx-language_list-created_by}}', '{{%language_list}}');
        \$this->dropIndex('{{%idx-language_list-updated_by}}', '{{%language_list}}');
        \$this->dropTable('{{%language_list}}');
    }
}
PHP;

        if (file_put_contents($filePath, $migrationCode) === false) {
            echo "Migration file yaratishda xato!\n";
            return;
        }

        echo "Migration fayli yaratildi: {$filePath}\n";
    }
}