import {MigrationInterface, QueryRunner} from "typeorm";

export class AddColorToBranch1603990658487 implements MigrationInterface {
    name = 'AddColorToBranch1603990658487'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch` ADD `color` varchar(255) NOT NULL DEFAULT '#FFFFFF'", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch` DROP COLUMN `color`", undefined);
    }

}
