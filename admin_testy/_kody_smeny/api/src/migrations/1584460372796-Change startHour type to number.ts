import {MigrationInterface, QueryRunner} from "typeorm";

export class ChangeStartHourTypeToNumber1584460372796 implements MigrationInterface {
    name = 'ChangeStartHourTypeToNumber1584460372796'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` CHANGE `maxUsers` `maxUsers` int NOT NULL DEFAULT 99999", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` DROP COLUMN `startHour`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD `startHour` int NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_7a21086c736e5b2c76a626a7978`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` CHANGE `branchId` `branchId` int NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_7a21086c736e5b2c76a626a7978` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_7a21086c736e5b2c76a626a7978`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` CHANGE `branchId` `branchId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_7a21086c736e5b2c76a626a7978` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` DROP COLUMN `startHour`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD `startHour` datetime NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `role` CHANGE `maxUsers` `maxUsers` int NOT NULL DEFAULT '0'", undefined);
    }

}
