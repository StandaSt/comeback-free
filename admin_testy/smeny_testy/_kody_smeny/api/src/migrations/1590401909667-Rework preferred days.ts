import {MigrationInterface, QueryRunner} from "typeorm";


enum Day {
    monday,
    tuesday,
    wednesday,
    thursday,
    friday,
    saturday,
    sunday,
}


export class ReworkPreferredDays1590401909667 implements MigrationInterface {
    name = 'ReworkPreferredDays1590401909667'
    dayList = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']


    public async up(queryRunner: QueryRunner): Promise<any> {
        const preferredWeeks = await queryRunner.query("SELECT * FROM `preferred_week`");

        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_01aa0dcd9d2d061e4f8e0efefaa`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_35efb48d23f67c23af9b114d371`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_45360815220a1a6c723c07ab30b`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_5e3f686d4d73b3d3eb814acd851`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_6e23cae4cec9e3fd76458444901`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_b38120f08abea50246d300fdec6`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP FOREIGN KEY `FK_f658053471a9b20d44185af7d18`", undefined);
        await queryRunner.query("DROP INDEX `REL_6e23cae4cec9e3fd7645844490` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_5e3f686d4d73b3d3eb814acd85` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_01aa0dcd9d2d061e4f8e0efefa` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_f658053471a9b20d44185af7d1` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_35efb48d23f67c23af9b114d37` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_45360815220a1a6c723c07ab30` ON `preferred_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_b38120f08abea50246d300fdec` ON `preferred_week`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `fridayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `mondayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `saturdayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `sundayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `thursdayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `tuesdayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` DROP COLUMN `wednesdayId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_day` ADD `day` int NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_day` ADD `preferredWeekId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_day` ADD CONSTRAINT `FK_273100f89d82c97105fd827522d` FOREIGN KEY (`preferredWeekId`) REFERENCES `preferred_week`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);

        for (const preferredWeek of preferredWeeks) {
            for (const dayName of this.dayList) {
                const dayId = preferredWeek[dayName + "Id"];
                const day = Day[dayName]
                await queryRunner.query("UPDATE `preferred_day` SET `day`=?, `preferredWeekId`=? WHERE `preferred_day`.id = ?", [day, preferredWeek.id, dayId])
            }
        }
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        const preferredDays = await queryRunner.query("SELECT * FROM `preferred_day`");

        await queryRunner.query("ALTER TABLE `preferred_day` DROP FOREIGN KEY `FK_273100f89d82c97105fd827522d`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_day` DROP COLUMN `preferredWeekId`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_day` DROP COLUMN `day`", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `wednesdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `tuesdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `thursdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `sundayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `saturdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `mondayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD `fridayId` int NULL", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_b38120f08abea50246d300fdec` ON `preferred_week` (`sundayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_45360815220a1a6c723c07ab30` ON `preferred_week` (`saturdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_35efb48d23f67c23af9b114d37` ON `preferred_week` (`fridayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_f658053471a9b20d44185af7d1` ON `preferred_week` (`thursdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_01aa0dcd9d2d061e4f8e0efefa` ON `preferred_week` (`wednesdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_5e3f686d4d73b3d3eb814acd85` ON `preferred_week` (`tuesdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_6e23cae4cec9e3fd7645844490` ON `preferred_week` (`mondayId`)", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_f658053471a9b20d44185af7d18` FOREIGN KEY (`thursdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_b38120f08abea50246d300fdec6` FOREIGN KEY (`sundayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_6e23cae4cec9e3fd76458444901` FOREIGN KEY (`mondayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_5e3f686d4d73b3d3eb814acd851` FOREIGN KEY (`tuesdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_45360815220a1a6c723c07ab30b` FOREIGN KEY (`saturdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_35efb48d23f67c23af9b114d371` FOREIGN KEY (`fridayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `preferred_week` ADD CONSTRAINT `FK_01aa0dcd9d2d061e4f8e0efefaa` FOREIGN KEY (`wednesdayId`) REFERENCES `preferred_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);

        for (const preferredDay of preferredDays) {
            const preferredDayWeekId = preferredDay.preferredWeekId;
            if (preferredDayWeekId) {
                const dayName = this.dayList[preferredDay.day]
                await queryRunner.query("UPDATE `preferred_week` SET " + dayName + "Id=? WHERE `preferred_week`.id = ?", [preferredDay.id, preferredDayWeekId])
            }
        }
    }

}
