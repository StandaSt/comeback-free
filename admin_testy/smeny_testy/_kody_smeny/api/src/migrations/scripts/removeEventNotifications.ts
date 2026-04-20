import {QueryRunner} from "typeorm";

const removeEventNotifications = async (queryRunner: QueryRunner, eventNames: string[]) => {
    await queryRunner.query("DELETE FROM event_notification WHERE eventName in (?)", [eventNames.join(",")]);
}

export default removeEventNotifications;