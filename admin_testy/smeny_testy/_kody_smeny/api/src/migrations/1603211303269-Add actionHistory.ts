import {MigrationInterface, QueryRunner} from "typeorm";

export class AddActionHistory1603211303269 implements MigrationInterface {
    name = 'AddActionHistory1603211303269'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `action_history` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `date` datetime NOT NULL, `additionalData` text NULL, `userId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `action_history` ADD CONSTRAINT `FK_27685181a4a900df56b0391131c` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `action_history` DROP FOREIGN KEY `FK_27685181a4a900df56b0391131c`", undefined);
        await queryRunner.query("DROP TABLE `action_history`", undefined);
    }

}
