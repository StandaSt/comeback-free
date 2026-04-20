import {MigrationInterface, QueryRunner} from "typeorm";
import ShiftWeek from "shiftWeek/shiftWeek.entity";
import ShiftDay from "../shiftDay/shiftDay.entity";

enum Day {
    monday,
    tuesday,
    wednesday,
    thursday,
    friday,
    saturday,
    sunday,
}


export class ReworkShiftDays1590137910166 implements MigrationInterface {
    name = 'ReworkShiftDays1590137910166'

    dayList = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']

    public async up(queryRunner: QueryRunner): Promise<any> {
        const shiftWeeks = await queryRunner.query("SELECT * FROM `shift_week`");

        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_0a75e1f264e1b7d2eb39508b2d5`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_1ec9078abd7a622d4c22b0267ad`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_2ba7733112630aae696f20009d2`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_3c6aa3f909c16ae16da2c6cb227`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_81d489e1ca50d4d5562cbda4aa5`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_95a140fbb6302c7e1410116c663`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_b252ac2a8ac7b0788f592b1c551`", undefined);
        await queryRunner.query("DROP INDEX `REL_0a75e1f264e1b7d2eb39508b2d` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_1ec9078abd7a622d4c22b0267a` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_95a140fbb6302c7e1410116c66` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_2ba7733112630aae696f20009d` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_81d489e1ca50d4d5562cbda4aa` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_3c6aa3f909c16ae16da2c6cb22` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_b252ac2a8ac7b0788f592b1c55` ON `shift_week`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `mondayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `tuesdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `wednesdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `thursdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `fridayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `saturdayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP COLUMN `sundayId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_day` ADD `day` int NOT NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_day` ADD `shiftWeekId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_day` ADD CONSTRAINT `FK_7bf70a4c5900cce4e36869fd963` FOREIGN KEY (`shiftWeekId`) REFERENCES `shift_week`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);


        for (const shiftWeek of shiftWeeks) {
            for (const dayName of this.dayList) {
                const dayId = shiftWeek[dayName + "Id"];
                const day = Day[dayName]
                await queryRunner.query("UPDATE `shift_day` SET `day`=?, `shiftWeekId`=? WHERE `shift_day`.id = ?", [day, shiftWeek.id, dayId])
            }
        }
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        const shiftDays = await queryRunner.query("SELECT * FROM `shift_day`");

        await queryRunner.query("ALTER TABLE `shift_day` DROP FOREIGN KEY `FK_7bf70a4c5900cce4e36869fd963`", undefined);
        await queryRunner.query("ALTER TABLE `shift_day` DROP COLUMN `shiftWeekId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_day` DROP COLUMN `day`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `sundayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `saturdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `fridayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `thursdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `wednesdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `tuesdayId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD `mondayId` int NULL", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_b252ac2a8ac7b0788f592b1c55` ON `shift_week` (`sundayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_3c6aa3f909c16ae16da2c6cb22` ON `shift_week` (`saturdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_81d489e1ca50d4d5562cbda4aa` ON `shift_week` (`fridayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_2ba7733112630aae696f20009d` ON `shift_week` (`thursdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_95a140fbb6302c7e1410116c66` ON `shift_week` (`wednesdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_1ec9078abd7a622d4c22b0267a` ON `shift_week` (`tuesdayId`)", undefined);
        await queryRunner.query("CREATE UNIQUE INDEX `REL_0a75e1f264e1b7d2eb39508b2d` ON `shift_week` (`mondayId`)", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_b252ac2a8ac7b0788f592b1c551` FOREIGN KEY (`sundayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_95a140fbb6302c7e1410116c663` FOREIGN KEY (`wednesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_81d489e1ca50d4d5562cbda4aa5` FOREIGN KEY (`fridayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_3c6aa3f909c16ae16da2c6cb227` FOREIGN KEY (`saturdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_2ba7733112630aae696f20009d2` FOREIGN KEY (`thursdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_1ec9078abd7a622d4c22b0267ad` FOREIGN KEY (`tuesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_0a75e1f264e1b7d2eb39508b2d5` FOREIGN KEY (`mondayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);

        for (const shiftDay of shiftDays) {
            const shiftDayWeekId = shiftDay.shiftWeekId;
            if (shiftDayWeekId) {
                const dayName = this.dayList[shiftDay.day]
                await queryRunner.query("UPDATE `shift_week` SET " + dayName + "Id=? WHERE `shift_week`.id = ?", [shiftDay.id, shiftDayWeekId])
            }
        }
    }
}
