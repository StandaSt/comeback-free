import {MigrationInterface, QueryRunner} from "typeorm";

export class AddMaxUsersToRole1584118470120 implements MigrationInterface {
    name = 'AddMaxUsersToRole1584118470120'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` ADD `maxUsers` int NOT NULL DEFAULT 0", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` DROP COLUMN `maxUsers`", undefined);
    }

}
