import {MigrationInterface, QueryRunner} from "typeorm";

export class AddMainBranchToUser1588152105373 implements MigrationInterface {
    name = 'AddMainBranchToUser1588152105373'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` ADD `dbMainBranchId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `user` ADD CONSTRAINT `FK_ba32343c585578005bc3e4e01de` FOREIGN KEY (`dbMainBranchId`) REFERENCES `branch`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP FOREIGN KEY `FK_ba32343c585578005bc3e4e01de`", undefined);
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `dbMainBranchId`", undefined);
    }

}
