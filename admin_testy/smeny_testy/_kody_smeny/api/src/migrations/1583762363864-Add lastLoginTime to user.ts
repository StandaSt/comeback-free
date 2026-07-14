import {MigrationInterface, QueryRunner} from "typeorm";

export class AddLastLoginTimeToUser1583762363864 implements MigrationInterface {
    name = 'AddLastLoginTimeToUser1583762363864'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` ADD `lastLoginTime` datetime NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `lastLoginTime`", undefined);
    }

}
