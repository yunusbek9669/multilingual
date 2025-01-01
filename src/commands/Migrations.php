<?php

namespace Yunusbek\Multilingual\commands;

use Yii;
use yii\console\Controller;

class Migrations extends Controller
{
    public function actionGenerate()
    {
        $migrationClassName = 'm' . gmdate('ymd_His') . '_create_multi_language_table';

        $filePath = Yii::getAlias('@app/migrations/' . $migrationClassName . '.php');

        // Migration faylini yaratish
        $migrationFile = fopen($filePath, 'w');
        if (!$migrationFile) {
            echo "Migration file yaratishda xato!\n";
            return;
        }

        // Migration kodini yozish
        $migrationCode = <<<PHP
<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%multi_language}}`.
 */
class {$migrationClassName} extends Migration
{
    public function safeUp()
    {
        // Create the multi_language table
        \$this->createTable('{{%multi_language}}', [
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

        // Create indexes and foreign keys
        \$this->createIndex('{{%idx-multi_language-status}}', '{{%multi_language}}', 'status');
        \$this->createIndex('{{%idx-multi_language-created_by}}', '{{%multi_language}}', 'created_by');
        \$this->createIndex('{{%idx-multi_language-updated_by}}', '{{%multi_language}}', 'updated_by');
    }

    public function safeDown()
    {
        // Drop foreign keys, indexes and table
        \$this->dropIndex('{{%idx-multi_language-status}}', '{{%multi_language}}');
        \$this->dropIndex('{{%idx-multi_language-created_by}}', '{{%multi_language}}');
        \$this->dropIndex('{{%idx-multi_language-updated_by}}', '{{%multi_language}}');
        \$this->dropTable('{{%multi_language}}');
    }
}
PHP;

        fwrite($migrationFile, $migrationCode);
        fclose($migrationFile);

        echo "Migration fayli yaratildi: {$migrationClassName}.php\n";
    }
}