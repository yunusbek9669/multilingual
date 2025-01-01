<?php

namespace Yunusbek\Multilingual\commands;

use Yii;
use yii\console\Controller;

class Migrations extends Controller
{
    public function actionGenerate()
    {
        $migrationClassName = 'm' . gmdate('ymd_His') . '_create_multi_language_table';

        $dir = Yii::getAlias('@app/migrations');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . '/' . $migrationClassName . '.php';

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

        \$this->createIndex('{{%idx-multi_language-status}}', '{{%multi_language}}', 'status');
        \$this->createIndex('{{%idx-multi_language-created_by}}', '{{%multi_language}}', 'created_by');
        \$this->createIndex('{{%idx-multi_language-updated_by}}', '{{%multi_language}}', 'updated_by');
    }

    public function safeDown()
    {
        \$this->dropIndex('{{%idx-multi_language-status}}', '{{%multi_language}}');
        \$this->dropIndex('{{%idx-multi_language-created_by}}', '{{%multi_language}}');
        \$this->dropIndex('{{%idx-multi_language-updated_by}}', '{{%multi_language}}');
        \$this->dropTable('{{%multi_language}}');
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