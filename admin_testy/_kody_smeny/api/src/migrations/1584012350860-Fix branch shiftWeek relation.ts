import {MigrationInterface, QueryRunner} from "typeorm";

export class FixBranchShiftWeekRelation1584012350860 implements MigrationInterface {
    name = 'FixBranchShiftWeekRelation1584012350860'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch` DROP FOREIGN KEY `FK_0860be8029cd43903e3f2462d81`", undefined);
        await queryRunner.query("ALTER TABLE `branch` DROP COLUMN `shiftWeeksId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `branchId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_7a21086c736e5b2c76a626a7978` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_7a21086c736e5b2c76a626a7978`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `branchId`", undefined);
        await queryRunner.query("ALTER TABLE `branch` ADD `shiftWeeksId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `branch` ADD CONSTRAINT `FK_0860be8029cd43903e3f2462d81` FOREIGN KEY (`shiftWeeksId`) REFERENCES `shift_week`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

}
