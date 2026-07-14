import {MigrationInterface, QueryRunner} from "typeorm";

export class AddEvaluation1604561751790 implements MigrationInterface {
    name = 'AddEvaluation1604561751790'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `evaluation` (`id` int NOT NULL AUTO_INCREMENT, `positive` tinyint NOT NULL, `date` datetime NOT NULL, `description` text NULL, `userId` int NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `evaluation` ADD CONSTRAINT `FK_115170ae291135522efdb1fb23c` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `evaluation` DROP FOREIGN KEY `FK_115170ae291135522efdb1fb23c`", undefined);
        await queryRunner.query("DROP TABLE `evaluation`", undefined);
    }

}
