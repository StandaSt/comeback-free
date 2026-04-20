import {MigrationInterface, QueryRunner} from "typeorm";

export class AddNameToTimeNotification1608199108010 implements MigrationInterface {
    name = 'AddNameToTimeNotification1608199108010'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `time_notification` ADD `name` varchar(255) NOT NULL");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `time_notification` DROP COLUMN `name`");
    }

}
