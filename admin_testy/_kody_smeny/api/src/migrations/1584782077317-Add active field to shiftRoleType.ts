import {MigrationInterface, QueryRunner} from "typeorm";

export class AddActiveFieldToShiftRoleType1584782077317 implements MigrationInterface {
    name = 'AddActiveFieldToShiftRoleType1584782077317'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` ADD `active` tinyint NOT NULL DEFAULT 1", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` DROP COLUMN `active`", undefined);
    }

}
