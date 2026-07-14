import {MigrationInterface, QueryRunner} from "typeorm";

export class AddMessageToTimeNotification1608217264128 implements MigrationInterface {
    name = 'AddMessageToTimeNotification1608217264128'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `time_notification` ADD `message` varchar(255) NOT NULL");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `time_notification` DROP COLUMN `message`");
    }

}
