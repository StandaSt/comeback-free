import {MigrationInterface, QueryRunner} from "typeorm";

export class AddPreferredEntities1586459402023 implements MigrationInterface {
    name = 'AddPreferredEntities1586459402023'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `preferred_hour` (`id` int NOT NULL AUTO_INCREMENT, `startHour` int NOT NULL, `preferredDayId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `preferred_day` (`id` int NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `preferred_week` (`id` int NOT NULL AUTO_INCREMENT, `startDay` datetime NOT NULL, `userId` int NULL, `mondayId` int NULL, `tuesdayId` int NULL, `wednesdayId` int NULL, `thursdayId` int NULL, `fridayId` int NULL, `saturdayId` int NULL, `sundayId` int NULL, UNIQUE INDEX `REL_6e23cae4cec9e3fd7645844490` (`mondayId`), UNIQUE INDEX `REL_5e3f686d4d73b3d3eb814acd85` (`tuesdayId`), UNIQUE INDEX `REL_01aa0dcd9d2d061e4f8e0efefa` (`wednesdayId`), UNIQUE INDEX `REL_f658053471a9b20d44185af7d1` (`thursdayId`), UNIQUE INDEX `REL_35efb48d23f67c23af9b114d37` (`fridayId`), UNIQUE INDEX `REL_45360815220a1a6c723c07ab30` (`saturdayId`), UNIQUE INDEX `REL_b38120f08abea50246d300fdec` (`sundayId`), PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `preferred_hour` ADD CONSTRAINT `FK_483a152e77cf9aa4f8508983833` FOREIGN KEY (`preferredDayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_4292e872b561b9333fd447d977d` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_6e23cae4cec9e3fd76458444901` FOREIGN KEY (`mondayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_5e3f686d4d73b3d3eb814acd851` FOREIGN KEY (`tuesdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_01aa0dcd9d2d061e4f8e0efefaa` FOREIGN KEY (`wednesdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_f658053471a9b20d44185af7d18` FOREIGN KEY (`thursdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_35efb48d23f67c23af9b114d371` FOREIGN KEY (`fridayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_45360815220a1a6c723c07ab30b` FOREIGN KEY (`saturdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_b38120f08abea50246d300fdec6` FOREIGN KEY (`sundayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_b38120f08abea50246d300fdec6`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_45360815220a1a6c723c07ab30b`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_35efb48d23f67c23af9b114d371`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_f658053471a9b20d44185af7d18`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_01aa0dcd9d2d061e4f8e0efefaa`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_5e3f686d4d73b3d3eb814acd851`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_6e23cae4cec9e3fd76458444901`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_4292e872b561b9333fd447d977d`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_hour` DROP FOREIGN KEY `FK_483a152e77cf9aa4f8508983833`", undefined);
        await queryRunner.query("DROP INDEX `REL_b38120f08abea50246d300fdec` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_45360815220a1a6c723c07ab30` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_35efb48d23f67c23af9b114d37` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_f658053471a9b20d44185af7d1` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_01aa0dcd9d2d061e4f8e0efefa` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_5e3f686d4d73b3d3eb814acd85` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_6e23cae4cec9e3fd7645844490` ON `preferred_week`", undefined);
        await queryRunner.query("DROP TABLE `preferred_week`", undefined);
        await queryRunner.query("DROP TABLE `preferred_day`", undefined);
        await queryRunner.query("DROP TABLE `preferred_hour`", undefined);
    }

}
