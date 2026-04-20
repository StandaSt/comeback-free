import {MigrationInterface, QueryRunner} from "typeorm";

export class AddActiveToUser1583590750109 implements MigrationInterface {
    name = 'AddActiveToUser1583590750109'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` ADD `active` tinyint NOT NULL DEFAULT 1", undefined);
        await queryRunner.query("ALTER TABLE `user` ADD `createTime` datetime NOT NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `createTime`", undefined);
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `active`", undefined);
    }

}
