import {MigrationInterface, QueryRunner} from "typeorm";

export class AddHalfHour1606288197910 implements MigrationInterface {
    name = 'AddHalfHour1606288197910'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `shift_role` ADD `halfHour` tinyint NOT NULL DEFAULT 0");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `shift_role` DROP COLUMN `halfHour`");
    }

}
