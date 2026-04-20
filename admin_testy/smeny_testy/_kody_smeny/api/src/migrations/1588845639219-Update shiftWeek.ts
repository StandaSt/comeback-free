import {MigrationInterface, QueryRunner} from "typeorm";

export class UpdateShiftWeek1588845639219 implements MigrationInterface {
    name = 'UpdateShiftWeek1588845639219'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_7a21086c736e5b2c76a626a7978`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` CHANGE `branchId` `branchId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_7273d8dab33125c573650026ec6`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` CHANGE `branchId` `branchId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_7a21086c736e5b2c76a626a7978` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_7273d8dab33125c573650026ec6` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_7273d8dab33125c573650026ec6`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_7a21086c736e5b2c76a626a7978`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` CHANGE `branchId` `branchId` int NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_7273d8dab33125c573650026ec6` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` CHANGE `branchId` `branchId` int NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_7a21086c736e5b2c76a626a7978` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

}
