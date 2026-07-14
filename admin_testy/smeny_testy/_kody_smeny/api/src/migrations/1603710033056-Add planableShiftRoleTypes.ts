import {MigrationInterface, QueryRunner} from "typeorm";

export class AddPlanableShiftRoleTypes1603710033056 implements MigrationInterface {
    name = 'AddPlanableShiftRoleTypes1603710033056'

    public async up(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("CREATE TABLE `shift_role_type_db_planners_user` (`shiftRoleTypeId` int NOT NULL, `userId` int NOT NULL, INDEX `IDX_37db2ff7b034163594ac90cf51` (`shiftRoleTypeId`), INDEX `IDX_ebfda80f4158a8714074d9a320` (`userId`), PRIMARY KEY (`shiftRoleTypeId`, `userId`)) ENGINE=InnoDB", undefined);
        await queryRunner.query("ALTER TABLE `shift_role_type_db_planners_user` ADD CONSTRAINT `FK_37db2ff7b034163594ac90cf515` FOREIGN KEY (`shiftRoleTypeId`) REFERENCES `shift_role_type`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
        await queryRunner.query("ALTER TABLE `shift_role_type_db_planners_user` ADD CONSTRAINT `FK_ebfda80f4158a8714074d9a320a` FOREIGN KEY (`userId`) REFERENCES `user`(`id`) ON DELETE CASCADE ON UPDATE NO ACTION", undefined);
    }

    public async down(queryRunner: QueryRunner): Promise<any> {
        await queryRunner.query("ALTER TABLE `shift_role_type_db_planners_user` DROP FOREIGN KEY `FK_ebfda80f4158a8714074d9a320a`", undefined);
        await queryRunner.query("ALTER TABLE `shift_role_type_db_planners_user` DROP FOREIGN KEY `FK_37db2ff7b034163594ac90cf515`", undefined);
        await queryRunner.query("DROP INDEX `IDX_ebfda80f4158a8714074d9a320` ON `shift_role_type_db_planners_user`", undefined);
        await queryRunner.query("DROP INDEX `IDX_37db2ff7b034163594ac90cf51` ON `shift_role_type_db_planners_user`", undefined);
        await queryRunner.query("DROP TABLE `shift_role_type_db_planners_user`", undefined);
    }

}
