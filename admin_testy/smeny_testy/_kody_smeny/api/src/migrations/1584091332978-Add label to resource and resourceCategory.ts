import {MigrationInterface, QueryRunner} from "typeorm";

export class AddLabelToResourceAndResourceCategory1584091332978 implements MigrationInterface {
    name = 'AddLabelToResourceAndResourceCategory1584091332978'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `resource_category` ADD `label` varchar(255) NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `resource` ADD `label` varchar(255) NOT NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `resource` DROP COLUMN `label`", undefined);
        await queryRunner.query("ALTER TABLE `resource_category` DROP COLUMN `label`", undefined);
    }

}
