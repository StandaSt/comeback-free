import {MigrationInterface, QueryRunner} from "typeorm";

export class AddSortIndexToShiftRoleType1589011120637 implements MigrationInterface {
    name = 'AddSortIndexToShiftRoleType1589011120637'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` ADD `sortIndex` int NOT NULL DEFAULT 0", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` DROP COLUMN `sortIndex`", undefined);
    }

}
