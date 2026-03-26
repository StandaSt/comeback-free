import {MigrationInterface, QueryRunner} from "typeorm";

export class AddEventNotification1607443608886 implements MigrationInterface {
    name = 'AddEventNotification1607443608886'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("CREATE TABLE `event_notification` (`id` int NOT NULL AUTO_INCREMENT, `eventName` varchar(255) NOT NULL, `message` varchar(255) NOT NULL, `label` varchar(255) NOT NULL, `description` varchar(255) NOT NULL, UNIQUE INDEX `IDX_3bc3b7546a0effd26414417903` (`eventName`), PRIMARY KEY (`id`)) ENGINE=InnoDB");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("DROP INDEX `IDX_3bc3b7546a0effd26414417903` ON `event_notification`");
        await queryRunner.query("DROP TABLE `event_notification`");
    }

}
