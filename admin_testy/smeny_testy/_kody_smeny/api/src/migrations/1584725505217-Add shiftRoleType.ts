import {MigrationInterface, QueryRunner} from "typeorm";

export class AddShiftRoleType1584725505217 implements MigrationInterface {
    name = 'AddShiftRoleType1584725505217'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role` CHANGE `name` `typeId` varchar(255) NOT NULL", undefined);
        await queryRunner.query("CREATE TABLE `shift_role_type` (`id` int NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` DROP COLUMN `typeId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` ADD `typeId` int NULL", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` ADD CONSTRAINT `FK_b40119b7042a9b40d4a2c16f746` FOREIGN KEY (`typeId`) REFERENCES `shift_role_type`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role` DROP FOREIGN KEY `FK_b40119b7042a9b40d4a2c16f746`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` DROP COLUMN `typeId`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` ADD `typeId` varchar(255) NOT NULL", undefined);
        await queryRunner.query("DROP TABLE `shift_role_type`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role` CHANGE `typeId` `name` varchar(255) NOT NULL", undefined);
    }

}
