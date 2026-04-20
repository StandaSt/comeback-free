import {MigrationInterface, QueryRunner} from "typeorm";

export class AddVariablesToEventNotification1607675659254 implements MigrationInterface {
    name = 'AddVariablesToEventNotification1607675659254'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `event_notification` ADD `variables` text");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `event_notification` DROP COLUMN `variables`");
    }

}
