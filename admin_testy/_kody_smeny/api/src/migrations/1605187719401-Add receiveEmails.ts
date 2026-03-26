import {MigrationInterface, QueryRunner} from "typeorm";

export class AddReceiveEmails1605187719401 implements MigrationInterface {
    name = 'AddReceiveEmails1605187719401'

    public async up(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `user` ADD `receiveEmails` tinyint NOT NULL DEFAULT 0");
    }

    public async down(queryRunner: QueryRunner): Promise<void> {
        await queryRunner.query("ALTER TABLE `user` DROP COLUMN `receiveEmails`");
    }

}
