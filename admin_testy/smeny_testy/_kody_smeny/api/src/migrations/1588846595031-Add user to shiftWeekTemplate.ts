import {MigrationInterface, QueryRunner} from "typeorm";

export class AddUserToShiftWeekTemplate1588846595031 implements MigrationInterface {
    name = 'AddUserToShiftWeekTemplate1588846595031'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` CHANGE `lastEdited` `userId` datetime NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `userId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `userId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_f0d2e45e55d17f557aa0e9f7c49` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_f0d2e45e55d17f557aa0e9f7c49`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `userId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `userId` datetime NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` CHANGE `userId` `lastEdited` datetime NOT NULL", undefined);
    }

}
