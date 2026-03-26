import {MigrationInterface, QueryRunner} from "typeorm";

export class AddSortIndexToRole1603962378750 implements MigrationInterface {
    name = 'AddSortIndexToRole1603962378750'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` ADD `sortIndex` int NOT NULL DEFAULT 0", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` DROP COLUMN `sortIndex`", undefined);
    }

}
