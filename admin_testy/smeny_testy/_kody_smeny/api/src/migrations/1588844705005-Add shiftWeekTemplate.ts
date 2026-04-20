import {MigrationInterface, QueryRunner} from "typeorm";

export class AddShiftWeekTemplate1588844705005 implements MigrationInterface {
    name = 'AddShiftWeekTemplate1588844705005'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `shift_week_template` (`id` int NOT NULL AUTO_INCREMENT, `startDay` datetime NOT NULL, `published` tinyint NOT NULL DEFAULT 0, `name` varchar(255) NOT NULL, `lastEdited` datetime NOT NULL, `mondayId` int NULL, `tuesdayId` int NULL, `wednesdayId` int NULL, `thursdayId` int NULL, `fridayId` int NULL, `saturdayId` int NULL, `sundayId` int NULL, `branchId` int NOT NULL, UNIQUE INDEX `REL_49b76478546318c26ed288c334` (`mondayId`), UNIQUE INDEX `REL_2a668ab2f54c61ff772f7a24a3` (`tuesdayId`), UNIQUE INDEX `REL_c9d7d09db866abda9394c6bf19` (`wednesdayId`), UNIQUE INDEX `REL_6480bcb467b4abd75656fac4a7` (`thursdayId`), UNIQUE INDEX `REL_b99a2fd6fcc72586f5ca9754a9` (`fridayId`), UNIQUE INDEX `REL_437496a1dff729eb006cf61b51` (`saturdayId`), UNIQUE INDEX `REL_a02c6054c62811286ac1f215f7` (`sundayId`), PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_49b76478546318c26ed288c334c` FOREIGN KEY (`mondayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_2a668ab2f54c61ff772f7a24a3b` FOREIGN KEY (`tuesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_c9d7d09db866abda9394c6bf19f` FOREIGN KEY (`wednesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_6480bcb467b4abd75656fac4a76` FOREIGN KEY (`thursdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_b99a2fd6fcc72586f5ca9754a90` FOREIGN KEY (`fridayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_437496a1dff729eb006cf61b51e` FOREIGN KEY (`saturdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_a02c6054c62811286ac1f215f75` FOREIGN KEY (`sundayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` ADD CONSTRAINT `FK_7273d8dab33125c573650026ec6` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_7273d8dab33125c573650026ec6`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_a02c6054c62811286ac1f215f75`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_437496a1dff729eb006cf61b51e`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_b99a2fd6fcc72586f5ca9754a90`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_6480bcb467b4abd75656fac4a76`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_c9d7d09db866abda9394c6bf19f`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_2a668ab2f54c61ff772f7a24a3b`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week_template` DROP FOREIGN KEY `FK_49b76478546318c26ed288c334c`", undefined);
        await queryRunner.query("DROP INDEX `REL_a02c6054c62811286ac1f215f7` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_437496a1dff729eb006cf61b51` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_b99a2fd6fcc72586f5ca9754a9` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_6480bcb467b4abd75656fac4a7` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_c9d7d09db866abda9394c6bf19` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_2a668ab2f54c61ff772f7a24a3` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP INDEX `REL_49b76478546318c26ed288c334` ON `shift_week_template`", undefined);
        await queryRunner.query("DROP TABLE `shift_week_template`", undefined);
    }

}
