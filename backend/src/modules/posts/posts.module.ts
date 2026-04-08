import { MongooseModule } from '@nestjs/mongoose';
import { Post, PostSchema } from './post.schema';
import { PostsService } from './posts.service';
import { PostsController } from './posts.controller';
import { Module } from '@nestjs/common';
import { LanguageService } from '../language/language.service';
import { PostSchedulerService } from '../services/post-scheduler.service';
import { ScheduleModule } from '@nestjs/schedule';

@Module({
  imports: [
    MongooseModule.forFeature([{ name: Post.name, schema: PostSchema }]),
    ScheduleModule.forRoot()
  ],
  providers: [PostsService, LanguageService, PostSchedulerService],
  controllers: [PostsController],
  exports: [MongooseModule.forFeature([{ name: Post.name, schema: PostSchema }])]
})
export class PostsModule {}
console.log('LOAD posts.module.ts', { pid: process.pid });
