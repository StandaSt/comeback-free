import {MigrationInterface, QueryRunner} from "typeorm";

export class MakeResourceCategoryNameUnique1583133780202 implements MigrationInterface {
    name = 'MakeResourceCategoryNameUnique1583133780202'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `resource_category` ADD UNIQUE INDEX `IDX_31c872a2e333ee155d60ccd454` (`name`)", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `resource_category` DROP INDEX `IDX_31c872a2e333ee155d60ccd454`", undefined);
    }

}
