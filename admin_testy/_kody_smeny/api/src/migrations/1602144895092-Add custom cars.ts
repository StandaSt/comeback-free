import {MigrationInterface, QueryRunner} from "typeorm";

export class AddCustomCars1602144895092 implements MigrationInterface {
    name = 'AddCustomCars1602144895092'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type` ADD `useCars` tinyint NOT NULL DEFAULT 0", undefined);
        await queryRunner.query("ALTER TABLE `user` ADD `hasOwnCar` tinyint NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `hasOwnCar`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role_type` DROP COLUMN `useCars`", undefined);
    }

}
