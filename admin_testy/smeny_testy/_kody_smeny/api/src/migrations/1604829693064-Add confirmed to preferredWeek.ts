import {MigrationInterface, QueryRunner} from "typeorm";

export class AddConfirmedToPreferredWeek1604829693064 implements MigrationInterface {
    name = 'AddConfirmedToPreferredWeek1604829693064'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `confirmed` tinyint NOT NULL DEFAULT 0");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `confirmed`");
    }

}
