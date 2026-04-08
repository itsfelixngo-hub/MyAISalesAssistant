import { Injectable } from '@nestjs/common';
import { InjectModel } from '@nestjs/mongoose';
import { Contact } from './contact.schema';
import { Model } from 'mongoose';
import { I18nService } from 'nestjs-i18n';
import { CreateContactDto } from './dtos/create.contact.dto';
import { ExErrorException } from 'src/common/error.filter';
import { FilterContactsDto } from './dtos/filter.contact.dto';
import { parseListString, parseNumberList } from 'src/utils/parse.util';
import { ReplyContactDto } from './dtos/reply.contact.dto';

@Injectable()
export class ContactService {
    constructor(
        @InjectModel(Contact.name) private readonly contactModel: Model<Contact>,
        private readonly i18n: I18nService
    ) { }

    async create(createContactDto: CreateContactDto, lang?: string): Promise<Contact> {
        try {
            const post = new this.contactModel(createContactDto);
            return await post.save();

        } catch (error) {
            const msg = this.i18n.translate('errors.CREATE_RECORD_FAIL', { lang });

            throw new ExErrorException(
                'CREATE_RECORD_FAIL',
                500,
                msg,
                error.message
            );
        }
    }

    async findAll(query: FilterContactsDto, lang?: string): Promise<{
        data: Contact[];
        meta: {
            page: number;
            limit: number;
            total: number;
            pageCount: number;
        };
    } | null> {

        try {
            const {
                page = 1,
                limit = 10,
                lang,
                status,
                program,
                school,
                keyword,
                startDate, endDate, sort = 'desc'
            } = query;

            const filter: Record<string, any> = {};
            const orFilters: Record<string, any>[] = [];

            if (lang) filter.lang = lang;

            const _status = parseListString(query.status);
            if (_status?.length) {
                if (_status.length > 1) {
                    orFilters.push(..._status.map((s) => ({ status: s })));
                } else {
                    filter.status = { $in: _status };
                }
            }

            if (orFilters.length) {
                filter.$or = orFilters;
            }
            const programs = parseNumberList(query.program);
            if (programs.length) filter.senderChooseProgram = { $in: programs };

            const schools = parseNumberList(query.school);
            if (schools.length) filter.senderChooseSchool = { $in: schools };

            if (keyword) {
                filter.$or = [
                    { senderName: { $regex: keyword, $options: 'i' } },
                    { senderMessage: { $regex: keyword, $options: 'i' } },
                ];
            }

            if (startDate || endDate) {
                filter.createdAt = {};

                if (startDate) {
                    const start = new Date(startDate);
                    start.setHours(0, 0, 0, 0);
                    filter.createdAt.$gte = start;
                }

                if (endDate) {
                    const end = new Date(endDate);
                    end.setHours(23, 59, 59, 999);
                    filter.createdAt.$lte = end;
                }
            }
            const total = await this.contactModel.countDocuments(filter);
            const data = await this.contactModel
                .find(filter)
                .populate('confirmBy', ['email', 'niceName', 'displayName', 'avatar'])
                .populate('approveBy', ['email', 'niceName', 'displayName', 'avatar'])
                .sort({ createdAt: sort === 'asc' ? 1 : -1 })
                .skip((page - 1) * limit)
                .limit(limit)
                .exec();

            return {
                data,
                meta: {
                    page,
                    limit,
                    total,
                    pageCount: Math.ceil(total / limit),
                },
            };
        } catch (error) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                500,
                msg,
                error.message
            );
        }
    }

    async findOnebyId(_id: string, lang?: string): Promise<Contact | null> {
        const result = await this.contactModel.findOne({ _id, lang }).populate('confirmBy', ['email', 'niceName', 'displayName', 'role']).exec();
        if (!result) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg,
            );
        }
        return result;
    }

    async approve(_id: string, userId: string, lang?: string): Promise<Contact | null> {
        const fetch = await this.contactModel.findOne({ _id, lang }).exec();
        if (!fetch) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                404,
                msg
            );
        }

        try {
            const result = await this.contactModel.findOneAndUpdate(
                { _id, lang },
                {
                    approveBy: userId,
                    status: 1,
                },
                { new: true }
            );

            return result;
        } catch (error) {
            const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException(
                'FETCH_RECORD_NOT_FOUND',
                500,
                msg,
                error.message
            );
        }
    }

    async reply(dto: ReplyContactDto, lang?: string): Promise<Contact | null> {
        const { id, confirmBy, confirmContent, status, processDate } = dto;
        console.log(dto);
        const contact = await this.contactModel.findOne({ _id: id, lang }).exec();
        if (!contact) {
            const msg = await this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
            throw new ExErrorException('FETCH_RECORD_NOT_FOUND', 404, msg);
        }

        try {
            const updated = await this.contactModel.findOneAndUpdate(
                { _id: id, lang },
                {
                    confirmBy,
                    processDate,
                    confirmContent,
                    status,
                },
                { new: true }
            );

            return updated;
        } catch (error) {
            const msg = this.i18n.translate('errors.UPDATE_FAILED', { lang });
            throw new ExErrorException('UPDATE_FAILED', 500, msg, error.message);
        }
    }
    
    async delete(id: string, lang?: string): Promise<Contact | null> {
        const fetch = await this.contactModel.findOne({ _id: id, lang }).exec();
        if (!fetch) {
          const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
          throw new ExErrorException(
            'FETCH_RECORD_NOT_FOUND',
            404,
            msg,
          );
        }
        try {
          await this.contactModel.deleteOne({ _id: id, lang }).exec();
          return fetch;
        } catch (error) {
          const msg = this.i18n.translate('errors.FETCH_RECORD_NOT_FOUND', { lang });
          throw new ExErrorException(
            'FETCH_RECORD_NOT_FOUND',
            404,
            msg
          );
        }
      }
}
