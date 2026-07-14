import {MigrationInterface, QueryRunner} from "typeorm";

export class MakeResourceAndRoleNameUnique1583134639548 implements MigrationInterface {
    name = 'MakeResourceAndRoleNameUnique1583134639548'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `role` ADD UNIQUE INDEX `IDX_ae4578dcaed5adff96595e6166` (`name`)", undefined);
        await queryRunner.query("ALTER TABLE `resource` ADD UNIQUE INDEX `IDX_c8ed18ff47475e2c4a7bf59daa` (`name`)", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `resource` DROP INDEX `IDX_c8ed18ff47475e2c4a7bf59daa`", undefined);
        await queryRunner.query("ALTER TABLE `role` DROP INDEX `IDX_ae4578dcaed5adff96595e6166`", undefined);
    }

}
