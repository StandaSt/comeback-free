import {MigrationInterface, QueryRunner} from "typeorm";

export class AddRegistrationDefault1588660855184 implements MigrationInterface {
    name = 'AddRegistrationDefault1588660855184'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` ADD `registrationDefault` tinyint NOT NULL DEFAULT 0", undefined);
        await queryRunner.query("ALTER TABLE `shift_role_type` ADD `registrationDefault` tinyint NOT NULL DEFAULT 0", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` DROP COLUMN `registrationDefault`", undefined);
        await queryRunner.query("ALTER TABLE `role` DROP COLUMN `registrationDefault`", undefined);
    }

}
