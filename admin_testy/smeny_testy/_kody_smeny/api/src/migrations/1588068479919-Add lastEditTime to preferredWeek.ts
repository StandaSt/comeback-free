import {MigrationInterface, QueryRunner} from "typeorm";

export class AddLastEditTimeToPreferredWeek1588068479919 implements MigrationInterface {
    name = 'AddLastEditTimeToPreferredWeek1588068479919'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `lastEditTime` datetime NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `lastEditTime`", undefined);
    }

}
