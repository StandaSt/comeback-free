import {MigrationInterface, QueryRunner} from "typeorm";

export class EditEvaluation1604598588591 implements MigrationInterface {
    name = 'EditEvaluation1604598588591'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `evaluation` ADD `evaluaterId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `evaluation` ADD CONSTRAINT `FK_3f92c0ed0dea2e08042f9256d9a` FOREIGN KEY (`evaluaterId`) REFERENCES `user`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `evaluation` DROP FOREIGN KEY `FK_3f92c0ed0dea2e08042f9256d9a`", undefined);
        await queryRunner.query("ALTER TABLE `evaluation` DROP COLUMN `evaluaterId`", undefined);
    }

}
