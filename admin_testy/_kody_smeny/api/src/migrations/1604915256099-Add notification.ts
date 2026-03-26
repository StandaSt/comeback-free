import {MigrationInterface, QueryRunner} from "typeorm";

export class AddNotification1604915256099 implements MigrationInterface {
    name = 'AddNotification1604915256099'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("CREATE TABLE `notification` (`id` int NOT NULL AUTO_INCREMENT, `subscription` text NOT NULL, `userId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB");
        await queryRunner.query("ALTER TABLE `notification` ADD CONSTRAINT `FK_1ced25315eb974b73391fb1c81b` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `notification` DROP FOREIGN KEY `FK_1ced25315eb974b73391fb1c81b`");
        await queryRunner.query("DROP TABLE `notification`");
    }

}
