import {MigrationInterface, QueryRunner} from "typeorm";

export class AddActiveFieldToBranch1585404056628 implements MigrationInterface {
    name = 'AddActiveFieldToBranch1585404056628'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch` ADD `active` tinyint NOT NULL DEFAULT 1", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch` DROP COLUMN `active`", undefined);
    }

}
