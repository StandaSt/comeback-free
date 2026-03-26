import {QueryRunner} from "typeorm";

const addEventNotifications = async (queryRunner: QueryRunner, eventNotifications: { eventName: string, message: string, label: string, description: string, variables?: { value: string, description: string }[] }[]) => {
    await queryRunner.query(`INSERT INTO event_notification (eventName, message, label, description, variables) VALUES ${eventNotifications.map((e) => `('${e.eventName}', '${e.message}', '${e.label}', '${e.description}', '${JSON.stringify(e.variables || [])}')`).join(",")}`);
}

export default addEventNotifications