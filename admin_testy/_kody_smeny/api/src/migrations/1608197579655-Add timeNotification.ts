import {MigrationInterface, QueryRunner} from "typeorm";

export class AddTimeNotification1608197579655 implements MigrationInterface {
    name = 'AddTimeNotification1608197579655'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("CREATE TABLE `time_notification_receiver` (`id` int NOT NULL AUTO_INCREMENT, `receiverGroupId` int NULL, `roleId` int NULL, `resourceId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        await queryRunner.query("CREATE TABLE `time_notification_receiver_group` (`id` int NOT NULL AUTO_INCREMENT, `notificationId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        await queryRunner.query("CREATE TABLE `time_notification` (`id` int NOT NULL AUTO_INCREMENT, `date` datetime NULL, `repeat` int NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        await queryRunner.query("ALTER TABLE `time_notification_receiver` ADD CONSTRAINT `FK_d93d313e6f889aadf899ef91d38` FOREIGN KEY (`receiverGroupId`) REFERENCES `time_notification_receiver_group`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION");
        await queryRunner.query("ALTER TABLE `time_notification_receiver` ADD CONSTRAINT `FK_2a7936ef2cfef0d5905603c7fc1` FOREIGN KEY (`roleId`) REFERENCES `role`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION");
        await queryRunner.query("ALTER TABLE `time_notification_receiver` ADD CONSTRAINT `FK_edf6d2aceb3f3b7306158c7a0f9` FOREIGN KEY (`resourceId`) REFERENCES `resource`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION");
        await queryRunner.query("ALTER TABLE `time_notification_receiver_group` ADD CONSTRAINT `FK_24b656452cd093cf7c0e2c3c495` FOREIGN KEY (`notificationId`) REFERENCES `time_notification`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `time_notification_receiver_group` DROP FOREIGN KEY `FK_24b656452cd093cf7c0e2c3c495`");
        await queryRunner.query("ALTER TABLE `time_notification_receiver` DROP FOREIGN KEY `FK_edf6d2aceb3f3b7306158c7a0f9`");
        await queryRunner.query("ALTER TABLE `time_notification_receiver` DROP FOREIGN KEY `FK_2a7936ef2cfef0d5905603c7fc1`");
        await queryRunner.query("ALTER TABLE `time_notification_receiver` DROP FOREIGN KEY `FK_d93d313e6f889aadf899ef91d38`");
        await queryRunner.query("DROP TABLE `time_notification`");
        await queryRunner.query("DROP TABLE `time_notification_receiver_group`");
        await queryRunner.query("DROP TABLE `time_notification_receiver`");
    }

}
