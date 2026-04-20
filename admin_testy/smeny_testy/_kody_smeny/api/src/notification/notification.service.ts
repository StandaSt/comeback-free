import { Injectable } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import webpush from 'web-push';
import { InjectSendGrid, SendGridService } from '@ntegral/nestjs-sendgrid';
import stringTemplate from 'string-template';

import User from 'user/user.entity';
import apiConfig from 'config/api';
import appConfig from 'config/app';
import EventNotificationService from 'eventNotification/eventNotification.service';

import Notification from './notification.entity';

@Injectable()
class NotificationService {
  constructor(
    @InjectRepository(Notification)
    private readonly notificationRepository: Repository<Notification>,
    @InjectSendGrid() private readonly sendGridService: SendGridService,
    private readonly eventNotificationService: EventNotificationService,
  ) {
    const vapidKeys = {
      publicKey: apiConfig.notifications.public,
      privateKey: apiConfig.notifications.private,
    };

    webpush.setVapidDetails(
      'https://smeny.pizzacomeback.cz',
      vapidKeys.publicKey,
      vapidKeys.privateKey,
    );
  }

  save(notification: Notification): Promise<Notification> {
    return this.notificationRepository.save(notification);
  }

  findBySubscription(subscription: string): Promise<Notification> {
    return this.notificationRepository.findOne({ subscription });
  }

  findByUserIds(userIds: number[]): Promise<Notification[]> {
    return this.notificationRepository
      .createQueryBuilder('notification')
      .innerJoin('notification.user', 'user')
      .where('user.id in (:...userIds)', { userIds })
      .andWhere('user.active = :active', { active: true })
      .getMany();
  }

  remove(notifications: Notification[]): Promise<Notification[]> {
    return this.notificationRepository.remove(notifications);
  }

  private async sendEmails(
    users: User[],
    message: string,
    redirect?: string,
  ): Promise<void> {
    const to = users
      .filter(u => u.receiveEmails && u.active)
      .map(u => ({ email: u.email }));

    if (apiConfig.sendgrid.sendEmail) {
      await this.sendGridService.send({
        from: apiConfig.sendgrid.from,
        personalizations: [
          {
            to,
            dynamicTemplateData: {
              title: appConfig.appName,
              message,
              redirect: `${appConfig.url}${redirect}`,
            },
          },
        ],
        templateId: apiConfig.sendgrid.templates.notification,
      });
    }
  }

  async sendEventNotifications(
    eventName: string,
    users: User[],
    redirect?: string,
    variables?: Record<string, string>,
  ): Promise<void> {
    const eventNotification = await this.eventNotificationService.findByEventName(
      eventName,
    );

    if (eventNotification) {
      await this.sendNotifications(
        users,
        eventNotification.message,
        redirect,
        variables,
      );
    }
  }

  async sendNotifications(
    users: User[],
    message: string,
    redirect?: string,
    variables?: Record<string, string>,
  ): Promise<void> {
    if (users.length > 0) {
      message = stringTemplate(message, variables || {});
      this.sendEmails(users, message, redirect);
      const notifications = await this.findByUserIds(users.map(u => u.id));
      for (const notification of notifications) {
        webpush
          .sendNotification(
            JSON.parse(notification.subscription),
            JSON.stringify({
              description: message,
              redirect: redirect || '/',
            }),
          )
          .catch(e => {
            if (
              e.statusCode === 410 ||
              e.statusCode === 404 ||
              e.statusCode === 400
            ) {
              this.remove([notification]);
            }
          });
      }
    }
  }
}

export default NotificationService;
