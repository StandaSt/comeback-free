import {MigrationInterface, QueryRunner} from "typeorm";

export class AddActiveShiftWeekTemplate1588870520913 implements MigrationInterface {
    name = 'AddActiveShiftWeekTemplate1588870520913'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `active` tinyint NOT NULL DEFAULT 1", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `active`", undefined);
    }

}
