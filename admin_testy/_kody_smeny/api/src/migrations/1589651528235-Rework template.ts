import {MigrationInterface, QueryRunner} from "typeorm";

export class ReworkTemplate1589651528235 implements MigrationInterface {
    name = 'ReworkTemplate1589651528235'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("DELETE FROM `shift_week_template`");
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_2a668ab2f54c61ff772f7a24a3b`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_437496a1dff729eb006cf61b51e`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_49b76478546318c26ed288c334c`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_6480bcb467b4abd75656fac4a76`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_7273d8dab33125c573650026ec6`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_a02c6054c62811286ac1f215f75`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_b99a2fd6fcc72586f5ca9754a90`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_c9d7d09db866abda9394c6bf19f`", undefined);
        await queryRunner.query("DROP INDEX `REL_49b76478546318c26ed288c334` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_2a668ab2f54c61ff772f7a24a3` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_c9d7d09db866abda9394c6bf19` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_6480bcb467b4abd75656fac4a7` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_b99a2fd6fcc72586f5ca9754a9` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_437496a1dff729eb006cf61b51` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_a02c6054c62811286ac1f215f7` ON `shift_week_template`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `branchId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `fridayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `mondayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `published`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `saturdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `startDay`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `sundayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `thursdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `tuesdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `waitingForApproval`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `wednesdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `shiftWeekId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD UNIQUE INDEX `IDX_acdfb75d47e1733e1f3baba16f` (`shiftWeekId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_acdfb75d47e1733e1f3baba16f` ON `shift_week_template` (`shiftWeekId`)", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_acdfb75d47e1733e1f3baba16fe` FOREIGN KEY (`shiftWeekId`) REFERENCES `shift_week`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_acdfb75d47e1733e1f3baba16fe`", undefined);
        await queryRunner.query("DROP INDEX `REL_acdfb75d47e1733e1f3baba16f` ON `shift_week_template`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP INDEX `IDX_acdfb75d47e1733e1f3baba16f`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP COLUMN `shiftWeekId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `wednesdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `waitingForApproval` tinyint NOT NULL DEFAULT '0'", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `tuesdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `thursdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `sundayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `startDay` datetime NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `saturdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `published` tinyint NOT NULL DEFAULT '0'", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `mondayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `fridayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD `branchId` int NULL", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_a02c6054c62811286ac1f215f7` ON `shift_week_template` (`sundayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_437496a1dff729eb006cf61b51` ON `shift_week_template` (`saturdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_b99a2fd6fcc72586f5ca9754a9` ON `shift_week_template` (`fridayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_6480bcb467b4abd75656fac4a7` ON `shift_week_template` (`thursdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_c9d7d09db866abda9394c6bf19` ON `shift_week_template` (`wednesdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_2a668ab2f54c61ff772f7a24a3` ON `shift_week_template` (`tuesdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_49b76478546318c26ed288c334` ON `shift_week_template` (`mondayId`)", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_c9d7d09db866abda9394c6bf19f` FOREIGN KEY (`wednesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_b99a2fd6fcc72586f5ca9754a90` FOREIGN KEY (`fridayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_a02c6054c62811286ac1f215f75` FOREIGN KEY (`sundayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_7273d8dab33125c573650026ec6` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_6480bcb467b4abd75656fac4a76` FOREIGN KEY (`thursdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_49b76478546318c26ed288c334c` FOREIGN KEY (`mondayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_437496a1dff729eb006cf61b51e` FOREIGN KEY (`saturdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_2a668ab2f54c61ff772f7a24a3b` FOREIGN KEY (`tuesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

}
