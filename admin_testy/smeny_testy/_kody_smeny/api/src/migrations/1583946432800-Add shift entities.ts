import {MigrationInterface, QueryRunner} from "typeorm";

export class AddShiftEntities1583946432800 implements MigrationInterface {
    name = 'AddShiftEntities1583946432800'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `shift_hour` (`id` int NOT NULL AUTO_INCREMENT, `startHour` datetime NOT NULL, `employeeId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `shift_role` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `shiftDayId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `shift_day` (`id` int NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `shift_week` (`id` int NOT NULL AUTO_INCREMENT, `startDay` datetime NOT NULL, `mondayId` int NULL, `tuesdayId` int NULL, `wednesdayId` int NULL, `thursdayId` int NULL, `fridayId` int NULL, `saturdayId` int NULL, `sundayId` int NULL, UNIQUE INDEX `REL_0a75e1f264e1b7d2eb39508b2d` (`mondayId`), UNIQUE INDEX `REL_1ec9078abd7a622d4c22b0267a` (`tuesdayId`), UNIQUE INDEX `REL_95a140fbb6302c7e1410116c66` (`wednesdayId`), UNIQUE INDEX `REL_2ba7733112630aae696f20009d` (`thursdayId`), UNIQUE INDEX `REL_81d489e1ca50d4d5562cbda4aa` (`fridayId`), UNIQUE INDEX `REL_3c6aa3f909c16ae16da2c6cb22` (`saturdayId`), UNIQUE INDEX `REL_b252ac2a8ac7b0788f592b1c55` (`sundayId`), PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `branch` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `shiftWeeksId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `branch_planners_user` (`branchId` int NOT NULL, `userId` int NOT NULL, INDEX `IDX_5ec8a4eeecbe47a04990ff5e6d` (`branchId`), INDEX `IDX_998981bc1696e0fa5b140be3b2` (`userId`), PRIMARY KEY (`branchId`, `userId`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` ADD CONSTRAINT `FK_e82135fa73801b18398c1af2a23` FOREIGN KEY (`employeeId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` ADD CONSTRAINT `FK_119da1cc5ee6d100ff6f0079697` FOREIGN KEY (`shiftDayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_0a75e1f264e1b7d2eb39508b2d5` FOREIGN KEY (`mondayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_1ec9078abd7a622d4c22b0267ad` FOREIGN KEY (`tuesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_95a140fbb6302c7e1410116c663` FOREIGN KEY (`wednesdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_2ba7733112630aae696f20009d2` FOREIGN KEY (`thursdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_81d489e1ca50d4d5562cbda4aa5` FOREIGN KEY (`fridayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_3c6aa3f909c16ae16da2c6cb227` FOREIGN KEY (`saturdayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` ADD CONSTRAINT `FK_b252ac2a8ac7b0788f592b1c551` FOREIGN KEY (`sundayId`) REFERENCES `shift_day`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `branch` ADD CONSTRAINT `FK_0860be8029cd43903e3f2462d81` FOREIGN KEY (`shiftWeeksId`) REFERENCES `shift_week`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `branch_planners_user` ADD CONSTRAINT `FK_5ec8a4eeecbe47a04990ff5e6d0` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `branch_planners_user` ADD CONSTRAINT `FK_998981bc1696e0fa5b140be3b26` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch_planners_user` DROP FOREIGN KEY `FK_998981bc1696e0fa5b140be3b26`", undefined);
        await queryRunner.query("ALTER TABLE `branch_planners_user` DROP FOREIGN KEY `FK_5ec8a4eeecbe47a04990ff5e6d0`", undefined);
        await queryRunner.query("ALTER TABLE `branch` DROP FOREIGN KEY `FK_0860be8029cd43903e3f2462d81`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_b252ac2a8ac7b0788f592b1c551`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_3c6aa3f909c16ae16da2c6cb227`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_81d489e1ca50d4d5562cbda4aa5`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_2ba7733112630aae696f20009d2`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_95a140fbb6302c7e1410116c663`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_1ec9078abd7a622d4c22b0267ad`", undefined);
        await queryRunner.query("ALTER TABLE `shift_week` DROP FOREIGN KEY `FK_0a75e1f264e1b7d2eb39508b2d5`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` DROP FOREIGN KEY `FK_119da1cc5ee6d100ff6f0079697`", undefined);
        await queryRunner.query("ALTER TABLE `shift_hour` DROP FOREIGN KEY `FK_e82135fa73801b18398c1af2a23`", undefined);
        await queryRunner.query("DROP INDEX `IDX_998981bc1696e0fa5b140be3b2` ON `branch_planners_user`", undefined);
        await queryRunner.query("DROP INDEX `IDX_5ec8a4eeecbe47a04990ff5e6d` ON `branch_planners_user`", undefined);
        await queryRunner.query("DROP TABLE `branch_planners_user`", undefined);
        await queryRunner.query("DROP TABLE `branch`", undefined);
        await queryRunner.query("DROP INDEX `REL_b252ac2a8ac7b0788f592b1c55` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_3c6aa3f909c16ae16da2c6cb22` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_81d489e1ca50d4d5562cbda4aa` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_2ba7733112630aae696f20009d` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_95a140fbb6302c7e1410116c66` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_1ec9078abd7a622d4c22b0267a` ON `shift_week`", undefined);
        await queryRunner.query("DROP INDEX `REL_0a75e1f264e1b7d2eb39508b2d` ON `shift_week`", undefined);
        await queryRunner.query("DROP TABLE `shift_week`", undefined);
        await queryRunner.query("DROP TABLE `shift_day`", undefined);
        await queryRunner.query("DROP TABLE `shift_role`", undefined);
        await queryRunner.query("DROP TABLE `shift_hour`", undefined);
    }

}
