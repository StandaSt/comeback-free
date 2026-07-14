import {MigrationInterface, QueryRunner} from "typeorm";

export class AddWorkersRealations1587111758992 implements MigrationInterface {
    name = 'AddWorkersRealations1587111758992'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `user_db_workers_shift_role_types_shift_role_type` (`userId` int NOT NULL, `shiftRoleTypeId` int NOT NULL, INDEX `IDX_5d3a994107be926713e7557c45` (`userId`), INDEX `IDX_439fcb1c03e19f3992cc295298` (`shiftRoleTypeId`), PRIMARY KEY (`userId`, `shiftRoleTypeId`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("CREATE TABLE `branch_db_workers_user` (`branchId` int NOT NULL, `userId` int NOT NULL, INDEX `IDX_a846b01b8a3026c24bad778e26` (`branchId`), INDEX `IDX_c307f1d6f409889bce4c5d6a9d` (`userId`), PRIMARY KEY (`branchId`, `userId`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `user_db_workers_shift_role_types_shift_role_type` ADD CONSTRAINT `FK_5d3a994107be926713e7557c45f` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `user_db_workers_shift_role_types_shift_role_type` ADD CONSTRAINT `FK_439fcb1c03e19f3992cc2952988` FOREIGN KEY (`shiftRoleTypeId`) REFERENCES `shift_role_type`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `branch_db_workers_user` ADD CONSTRAINT `FK_a846b01b8a3026c24bad778e26f` FOREIGN KEY (`branchId`) REFERENCES `branch`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `branch_db_workers_user` ADD CONSTRAINT `FK_c307f1d6f409889bce4c5d6a9d9` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `branch_db_workers_user` DROP FOREIGN KEY `FK_c307f1d6f409889bce4c5d6a9d9`", undefined);
        await queryRunner.query("ALTER TABLE `branch_db_workers_user` DROP FOREIGN KEY `FK_a846b01b8a3026c24bad778e26f`", undefined);
        await queryRunner.query("ALTER TABLE `user_db_workers_shift_role_types_shift_role_type` DROP FOREIGN KEY `FK_439fcb1c03e19f3992cc2952988`", undefined);
        await queryRunner.query("ALTER TABLE `user_db_workers_shift_role_types_shift_role_type` DROP FOREIGN KEY `FK_5d3a994107be926713e7557c45f`", undefined);
        await queryRunner.query("DROP INDEX `IDX_c307f1d6f409889bce4c5d6a9d` ON `branch_db_workers_user`", undefined);
        await queryRunner.query("DROP INDEX `IDX_a846b01b8a3026c24bad778e26` ON `branch_db_workers_user`", undefined);
        await queryRunner.query("DROP TABLE `branch_db_workers_user`", undefined);
        await queryRunner.query("DROP INDEX `IDX_439fcb1c03e19f3992cc295298` ON `user_db_workers_shift_role_types_shift_role_type`", undefined);
        await queryRunner.query("DROP INDEX `IDX_5d3a994107be926713e7557c45` ON `user_db_workers_shift_role_types_shift_role_type`", undefined);
        await queryRunner.query("DROP TABLE `user_db_workers_shift_role_types_shift_role_type`", undefined);
    }

}
