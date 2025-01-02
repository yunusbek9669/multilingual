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
            'name' => \$this->string(30),
            'short_name' => \$this->string(5),
            'key' => \$this->string(5),
            'image' => \$this->string(50),
            'table' => \$this->string(50),
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