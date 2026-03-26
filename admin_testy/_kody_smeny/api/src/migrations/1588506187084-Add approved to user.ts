import {MigrationInterface, QueryRunner} from "typeorm";

export class AddApprovedToUser1588506187084 implements MigrationInterface {
    name = 'AddApprovedToUser1588506187084'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` ADD `approved` tinyint NOT NULL DEFAULT 1", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `approved`", undefined);
    }

}
