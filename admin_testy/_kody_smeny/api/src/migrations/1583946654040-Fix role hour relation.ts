import {MigrationInterface, QueryRunner} from "typeorm";

export class FixRoleHourRelation1583946654040 implements MigrationInterface {
    name = 'FixRoleHourRelation1583946654040'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_hour` ADD `shiftRoleId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD CONSTRAINT `FK_c478c984de48bd617d1703a3e92` FOREIGN KEY (`shiftRoleId`) REFERENCES `shift_role`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_hour` DROP FOREIGN KEY `FK_c478c984de48bd617d1703a3e92`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` DROP COLUMN `shiftRoleId`", undefined);
    }

}
