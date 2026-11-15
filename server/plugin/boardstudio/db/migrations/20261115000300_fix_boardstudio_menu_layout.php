<?php

declare(strict_types=1);
namespace plugin\boardstudio\db\migrations;

use Phinx\Migration\AbstractMigration;

final class FixBoardstudioMenuLayout extends AbstractMigration
{
    public function up(): void
    {
        $this->getUpdateBuilder()->update('sa_system_menu')->set(['component' => '/index/index'])
            ->where(['code' => 'BoardStudio', 'remark' => 'boardstudio:install:1', 'component' => ''])->execute();
    }

    public function down(): void
    {
        // Keep the valid layout during rollback: the previous migration may
        // refuse uninstall while business data exists. The base migration
        // removes this owned menu when uninstall is allowed to complete.
    }
}
