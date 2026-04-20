import {MigrationInterface, QueryRunner} from "typeorm";

export class AddPhoneNumber1602165626918 implements MigrationInterface {
    name = 'AddPhoneNumber1602165626918'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` ADD `phoneNumber` varchar(255) NULL", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `phoneNumber`", undefined);
    }

}
