import {MigrationInterface, QueryRunner} from "typeorm";

export class AddColorToShiftRoleType1589964009360 implements MigrationInterface {
    name = 'AddColorToShiftRoleType1589964009360'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` ADD `color` varchar(255) NOT NULL DEFAULT '#FFFFFF'", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` DROP COLUMN `color`", undefined);
    }

}
